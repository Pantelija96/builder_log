<?php

namespace App\Services\Database;

use Generator;
use PDO;
use RuntimeException;

class ClientDatabaseValidator
{
    public const EXCLUDED = ['cache', 'cache_locks', 'migrations', 'personal_access_tokens', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public const OPERATIONAL = ['jobs', 'job_batches', 'failed_jobs'];

    public const ORDER = [
        'companies', 'workers', 'construction_sites', 'subcontractors', 'suppliers', 'initial_data_imports',
        'daily_logs', 'construction_site_worker', 'machines', 'tasks', 'documents', 'cash_advances',
        'worker_payments', 'worker_devices', 'daily_log_site_managers', 'delivery_notes', 'excavators',
        'trucks', 'machine_assignments', 'expenses', 'notes', 'notifications', 'subcontractor_logs',
        'attachments', 'excavator_logs', 'truck_logs', 'worker_attendances', 'audit_logs', 'activity_logs',
    ];

    public function reference(): array
    {
        $reference = json_decode(file_get_contents(database_path('schema/client-transfer.json')), true, flags: JSON_THROW_ON_ERROR);
        $migrations = [];
        foreach (glob(database_path('migrations/*.php')) as $file) {
            $migrations[basename($file, '.php')] = hash_file('sha256', $file);
        }
        if ($migrations !== $reference['migrations']) {
            throw new RuntimeException('Migration files differ from the reviewed transfer reference.');
        }

        return $reference;
    }

    public function schema(PDO $pdo): array
    {
        $read = fn ($sql) => $pdo->query($sql)->fetchAll(PDO::FETCH_NUM);
        $tables = [];
        foreach ($read('SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME') as [$table, $engine, $collation]) {
            $tables[$table] = ['engine' => $engine, 'collation' => $collation, 'columns' => [], 'indexes' => [], 'foreign' => []];
        }
        foreach ($read('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION') as $row) {
            [$table, $column] = $row;
            $tables[$table]['columns'][$column] = array_slice($row, 2);
        }
        foreach ($read('SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,INDEX_TYPE,COLUMN_NAME,SUB_PART,COLLATION FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX') as $row) {
            [$table, $index, $nonUnique, $type] = $row;
            $column = array_slice($row, 4);
            $tables[$table]['indexes'][$index] ??= [$nonUnique === '0', $type, []];
            $tables[$table]['indexes'][$index][2][] = $column;
        }
        foreach ($read('SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,k.REFERENCED_TABLE_NAME,r.UPDATE_RULE,r.DELETE_RULE,k.COLUMN_NAME,k.REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION') as $row) {
            [$table, $name, $parent, $update, $delete] = $row;
            $columns = array_slice($row, 5);
            $tables[$table]['foreign'][$name] ??= [$parent, $update, $delete, []];
            $tables[$table]['foreign'][$name][3][] = $columns;
        }

        if ($pdo->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE()
            AND CONSTRAINT_TYPE NOT IN ('PRIMARY KEY', 'UNIQUE', 'FOREIGN KEY') LIMIT 1")->fetchColumn()
            || $pdo->query('SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() LIMIT 1')->fetchColumn()) {
            throw new RuntimeException('Unexpected check constraints or triggers in transfer schema.');
        }

        return $tables;
    }

    public function compatible(array $schema, array $reference, bool $source): void
    {
        $expected = $reference['tables'];
        if ($source) {
            if (! isset($schema['daily_log_site_managers'])) {
                if (! isset($schema['daily_logs']['columns']['site_manager_id'])) {
                    throw new RuntimeException('No source of daily log management relationships.');
                }
                unset($expected['daily_log_site_managers']);
            }
            if (! isset($schema['workers']['columns']['display_order'])) {
                unset($expected['workers']['columns']['display_order']);
            } else {
                $oldDefault = $schema['workers']['columns']['display_order'][2];
                if (in_array($oldDefault, ['0', '1'], true)) {
                    $expected['workers']['columns']['display_order'][2] = $oldDefault;
                }
            }
            if (isset($schema['daily_logs']['columns']['site_manager_id'])) {
                $expected['daily_logs']['columns']['site_manager_id'] = ['bigint unsigned', 'NO', null, '', null, ''];
                $expected['daily_logs']['foreign']['daily_logs_site_manager_id_foreign'] = ['workers', 'CASCADE', 'RESTRICT', [['site_manager_id', 'id']]];
            }
            if (! isset($schema['worker_attendances']['indexes']['worker_attendances_worker_interval_index'])) {
                unset($expected['worker_attendances']['indexes']['worker_attendances_worker_interval_index']);
            }
        }
        if (array_diff_key($schema, $expected) || array_diff_key($expected, $schema)) {
            throw new RuntimeException('Unexpected or missing tables in transfer schema.');
        }
        foreach ($expected as $table => $definition) {
            $actual = $schema[$table];
            foreach (['engine', 'collation', 'columns', 'foreign'] as $key) {
                if ($actual[$key] != $definition[$key]) {
                    throw new RuntimeException("Incompatible $key in $table.");
                }
            }
            // Required indexes are structural. InnoDB can add or remove redundant FK supporting indexes.
            foreach ($definition['indexes'] as $name => $index) {
                if (! in_array($index, $actual['indexes'], true) && ! $this->supportingIndex($index, $definition['foreign'], $actual['indexes'])) {
                    throw new RuntimeException("Missing expected index $table.$name.");
                }
                if ($name === 'PRIMARY' && ($actual['indexes']['PRIMARY'] ?? null) !== $index) {
                    throw new RuntimeException("Incorrect primary key in $table.");
                }
            }
            foreach ($actual['indexes'] as $name => $index) {
                if (! in_array($index, $definition['indexes'], true) && ! $this->supportingIndex($index, $definition['foreign'], $actual['indexes'])) {
                    throw new RuntimeException("Unexpected index $table.$name.");
                }
            }
        }
    }

    private function supportingIndex(array $index, array $foreignKeys, array $available): bool
    {
        [$unique, $type, $columns] = $index;
        if ($unique || $type !== 'BTREE') {
            return false;
        }
        foreach ($foreignKeys as [, , , $pairs]) {
            if ($columns !== array_map(fn ($pair) => [$pair[0], null, 'A'], $pairs)) {
                continue;
            }
            foreach ($available as [, $kind, $candidate]) {
                if ($kind === 'BTREE' && array_slice($candidate, 0, count($columns)) === $columns) {
                    return true;
                }
            }
        }

        return false;
    }

    public function checkForeignKeys(PDO $pdo, array $schema): void
    {
        foreach ($schema as $table => $definition) {
            foreach ($definition['foreign'] as $name => [$parent, , , $pairs]) {
                $equal = [];
                $nonnull = [];
                foreach ($pairs as [$column, $parentColumn]) {
                    $equal[] = 'p.'.$this->id($parentColumn).'=c.'.$this->id($column);
                    $nonnull[] = 'c.'.$this->id($column).' IS NOT NULL';
                }
                $sql = 'SELECT 1 FROM '.$this->id($table).' c WHERE '.implode(' AND ', $nonnull).
                    ' AND NOT EXISTS (SELECT 1 FROM '.$this->id($parent).' p WHERE '.implode(' AND ', $equal).') LIMIT 1';
                if ($pdo->query($sql)->fetchColumn() !== false) {
                    throw new RuntimeException("Foreign key violation: $table.$name.");
                }
            }
        }
    }

    public function exceptions(PDO $pdo): array
    {
        $queries = [
            'excavator_finish_without_start' => 'SELECT id FROM excavator_logs WHERE site_manager_started_at IS NULL AND site_manager_finished_at IS NOT NULL ORDER BY id',
            'orphan_audit_attendance' => "SELECT l.id,l.auditable_id FROM audit_logs l LEFT JOIN worker_attendances a ON a.id=l.auditable_id WHERE l.auditable_type='worker_attendance' AND a.id IS NULL ORDER BY l.id",
            'orphan_activity_attendance' => "SELECT l.id,l.subject_id FROM activity_logs l LEFT JOIN worker_attendances a ON a.id=l.subject_id WHERE l.subject_type='worker_attendance' AND a.id IS NULL ORDER BY l.id",
            'same_day_attendance' => 'SELECT company_id,worker_id,date,COUNT(*) AS records FROM worker_attendances GROUP BY company_id,worker_id,date HAVING COUNT(*)>1 ORDER BY company_id,worker_id,date',
            'reservation_overlaps' => 'WITH periods AS (
                SELECT a.id,a.company_id,a.machine_id,a.worker_id,COALESCE(l.s,CAST(DATE(a.date) AS DATETIME)) AS s,
                    IF(l.s IS NULL OR l.f IS NULL, DATE_ADD(DATE(a.date),INTERVAL 1 DAY),l.f) AS e
                FROM machine_assignments a JOIN (
                    SELECT machine_assignment_id,site_manager_started_at s,site_manager_finished_at f FROM truck_logs WHERE deleted_at IS NULL
                    UNION ALL SELECT machine_assignment_id,site_manager_started_at,site_manager_finished_at FROM excavator_logs WHERE deleted_at IS NULL
                ) l ON l.machine_assignment_id=a.id WHERE a.deleted_at IS NULL
            ) SELECT a.id AS left_id,b.id AS right_id FROM periods a JOIN periods b
                ON b.id>a.id AND a.company_id=b.company_id AND a.s<b.e AND b.s<a.e
                WHERE a.machine_id=b.machine_id OR a.worker_id=b.worker_id ORDER BY a.id,b.id',
        ];

        $aliases = [
            'attachment' => 'attachments', 'cash_advance' => 'cash_advances', 'company' => 'companies',
            'construction_site' => 'construction_sites', 'daily_log' => 'daily_logs', 'delivery_note' => 'delivery_notes',
            'document' => 'documents', 'excavator_log' => 'excavator_logs', 'expense' => 'expenses', 'machine' => 'machines',
            'machine_assignment' => 'machine_assignments', 'note' => 'notes', 'subcontractor' => 'subcontractors',
            'subcontractor_log' => 'subcontractor_logs', 'supplier' => 'suppliers', 'task' => 'tasks',
            'truck_log' => 'truck_logs', 'worker' => 'workers', 'worker_attendance' => 'worker_attendances',
        ];
        foreach (['attachments' => 'attachable', 'machines' => 'owner', 'audit_logs' => 'auditable', 'activity_logs' => 'subject'] as $table => $prefix) {
            $type = $prefix.'_type';
            $id = $prefix.'_id';
            $parts = [];
            foreach ($aliases as $alias => $parent) {
                $parts[] = "(BINARY c.$type='$alias' AND EXISTS (SELECT 1 FROM $parent p WHERE p.id=c.$id))";
            }
            $queries['polymorphic_exceptions_'.$table] = "SELECT c.id,c.$type,c.$id FROM $table c WHERE
                NOT (c.$type IS NULL AND c.$id IS NULL) AND
                (c.$type IS NULL OR c.$id IS NULL OR NOT (".implode(' OR ', $parts).')) ORDER BY c.id';
        }

        $exceptions = [];
        $pdo->setAttribute(PDO\Mysql::ATTR_USE_BUFFERED_QUERY, false);
        try {
            foreach ($queries as $name => $sql) {
                $statement = $pdo->query($sql);
                $hash = hash_init('sha256');
                $count = 0;
                try {
                    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                        hash_update($hash, $this->line($row));
                        $count++;
                    }
                } finally {
                    $statement->closeCursor();
                }
                $exceptions[$name] = ['count' => $count, 'sha256' => hash_final($hash)];
            }
        } finally {
            $pdo->setAttribute(PDO\Mysql::ATTR_USE_BUFFERED_QUERY, true);
        }

        return $exceptions;
    }

    public function validateMemberships(string $path, array $schema): int
    {
        // Use disk-backed identifiers so preflight stays bounded with large customer histories.
        $temporary = tempnam(sys_get_temp_dir(), 'builderlog-preflight-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot allocate private preflight workspace.');
        }
        $db = null;
        try {
            $db = new PDO('sqlite:'.$temporary);
            $schema = array_diff_key($schema, array_flip(self::EXCLUDED));
            $db->beginTransaction();
            $columns = [
                'workers' => ['company_id' => true],
                'daily_logs' => ['company_id' => true, 'site_manager_id' => true],
            ];
            foreach ($schema as $table => $definition) {
                foreach ($definition['foreign'] as [$parent, , , $pairs]) {
                    foreach ($pairs as [$childColumn, $parentColumn]) {
                        $columns[$table][$childColumn] = true;
                        $columns[$parent][$parentColumn] = true;
                    }
                }
                foreach ($definition['indexes'] as [$unique, , $parts]) {
                    if ($unique) {
                        foreach ($parts as [$column]) {
                            $columns[$table][$column] = true;
                        }
                    }
                }
            }
            foreach ($schema as $table => $definition) {
                $keys = array_keys($columns[$table]);
                $fields = array_map(fn ($key) => $this->id($key).' TEXT', $keys);
                foreach ($definition['indexes'] as $name => [$unique, , $parts]) {
                    if ($unique) {
                        $kind = $name === 'PRIMARY' ? 'PRIMARY KEY' : 'UNIQUE';
                        $fields[] = $kind.' ('.implode(',', array_map(fn ($part) => $this->id($part[0]), $parts)).')';
                    }
                }
                $quoted = $this->id($table);
                $db->exec("CREATE TABLE $quoted (".implode(',', $fields).') WITHOUT ROWID');
                $placeholders = implode(',', array_fill(0, count($keys), '?'));
                $insert = $db->prepare("INSERT INTO $quoted VALUES ($placeholders)");
                try {
                    foreach ($this->rowsFromFile($path, $table, $definition['columns']) as $row) {
                        $insert->execute(array_map(fn ($key) => $row[$key] ?? null, $keys));
                    }
                } catch (\PDOException $exception) {
                    throw new RuntimeException("Duplicate or invalid key in $table.", previous: $exception);
                }
            }
            if (! isset($schema['daily_log_site_managers'])) {
                $db->exec('CREATE TABLE daily_log_site_managers (daily_log_id TEXT, site_manager_id TEXT)');
            }
            $db->commit();
            $this->checkForeignKeys($db, $schema);
            if ($db->query('SELECT 1 FROM daily_logs l LEFT JOIN workers w ON w.id=l.site_manager_id
                WHERE l.site_manager_id IS NOT NULL AND (w.id IS NULL OR w.company_id<>l.company_id) LIMIT 1')->fetchColumn()
                || $db->query('SELECT 1 FROM daily_log_site_managers m LEFT JOIN daily_logs l ON l.id=m.daily_log_id
                LEFT JOIN workers w ON w.id=m.site_manager_id
                WHERE l.id IS NULL OR w.id IS NULL OR w.company_id<>l.company_id LIMIT 1')->fetchColumn()) {
                throw new RuntimeException('Daily log membership has a missing or cross-company reference.');
            }

            return (int) $db->query('SELECT COUNT(*) FROM daily_logs l WHERE l.site_manager_id IS NOT NULL AND
                NOT EXISTS (SELECT 1 FROM daily_log_site_managers m WHERE m.daily_log_id=l.id AND m.site_manager_id=l.site_manager_id)')->fetchColumn();
        } finally {
            unset($db);
            unlink($temporary);
        }
    }

    public function counters(PDO $pdo): array
    {
        return $pdo->query('SELECT TABLE_NAME,AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND AUTO_INCREMENT IS NOT NULL')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function id(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    public function rows(PDO $pdo, string $table, array $definition, array $columns): Generator
    {
        $fields = implode(',', array_map($this->id(...), $columns));
        $primary = $definition['indexes']['PRIMARY'][2] ?? [];
        if ($primary === []) {
            throw new RuntimeException("No stable primary key in $table.");
        }
        $keys = implode(',', array_map(fn ($part) => $this->id($part[0]), $primary));
        $pdo->setAttribute(PDO\Mysql::ATTR_USE_BUFFERED_QUERY, false);
        $statement = null;
        try {
            $statement = $pdo->query('SELECT '.$fields.' FROM '.$this->id($table).' ORDER BY '.$keys);
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                yield $row;
            }
        } finally {
            $statement?->closeCursor();
            $pdo->setAttribute(PDO\Mysql::ATTR_USE_BUFFERED_QUERY, true);
        }
    }

    public function line(array $row): string
    {
        // Encode database text bytes as well as binary bytes. SQL NULL retains its own representation.
        return json_encode(array_map(fn ($value) => $value === null ? null : base64_encode($value), $row), JSON_THROW_ON_ERROR)."\n";
    }

    public function decode(string $line, array $columns, string $table): array
    {
        $encoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($encoded) || array_keys($encoded) !== array_keys($columns)) {
            throw new RuntimeException("Invalid column layout for $table.");
        }

        $row = [];
        foreach ($columns as $key => [$type, $nullable]) {
            $value = $encoded[$key];
            if ($value === null && $nullable === 'YES') {
                $row[$key] = null;

                continue;
            }
            $bytes = is_string($value) ? base64_decode($value, true) : false;
            if ($bytes === false || base64_encode($bytes) !== $value) {
                throw new RuntimeException("Invalid value encoding for $table.$key.");
            }
            if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)(?:\b|\()/', $type) && ! preg_match('/\A-?\d+\z/', $bytes)) {
                throw new RuntimeException("Invalid integer for $table.$key.");
            }
            if (str_starts_with($type, 'decimal') && ! preg_match('/\A-?\d+(\.\d+)?\z/', $bytes)) {
                throw new RuntimeException("Invalid decimal for $table.$key.");
            }
            if ($type === 'json' && ! json_validate($bytes)) {
                throw new RuntimeException("Invalid JSON for $table.$key.");
            }
            $row[$key] = $bytes;
        }

        return $row;
    }

    public function rowsFromFile(string $path, string $table, array $columns): Generator
    {
        $handle = fopen($path.'/'.$table.'.jsonl', 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot read $table.");
        }
        try {
            while (($line = fgets($handle)) !== false) {
                if (! str_ends_with($line, "\n")) {
                    throw new RuntimeException("Incomplete row in $table.");
                }
                yield $this->decode($line, $columns, $table);
            }
            if (! feof($handle)) {
                throw new RuntimeException("Incomplete read of $table.");
            }
        } finally {
            fclose($handle);
        }
    }
}
