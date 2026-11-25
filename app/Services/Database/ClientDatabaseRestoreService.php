<?php

namespace App\Services\Database;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class ClientDatabaseRestoreService
{
    public function __construct(private ClientDatabaseConnection $connections, private ClientDatabaseValidator $validator) {}

    public function restore(string $client, string $export, string $alias, bool $dryRun, Closure $stage): array
    {
        $stage('preflight');
        $destinationSettings = $this->connections->settings($client, 'destination', $alias);
        if (! Str::isUuid($export)) {
            throw new RuntimeException('Select a valid export UUID.');
        }
        $path = rtrim(config('client_database.storage'), '/')."/$client/$export";
        $reference = $this->validator->reference();
        $manifest = $this->package($path, $client, $export, $reference);
        $sourceSettings = $this->connections->settings($client, 'source', $manifest['source']['alias'] ?? '');
        $source = $this->connections->source($sourceSettings);
        try {
            $sourceIdentity = $this->connections->identity($source->getPdo(), $sourceSettings);
            if ($sourceIdentity !== $manifest['source']['identity']) {
                throw new RuntimeException('Current source identity differs from the export manifest.');
            }
        } finally {
            $source->disconnect();
        }

        $connection = $this->connections->destination($destinationSettings);
        $pdo = null;
        $locked = false;
        try {
            $pdo = $connection->getPdo();
            $target = $this->connections->identity($pdo, $destinationSettings);
            $this->connections->assertDistinct($sourceIdentity, $target);
            $default = DB::connection()->getDatabaseName();
            if (strcasecmp($default, $target['db']) === 0) {
                throw new RuntimeException('Destination database name matches the application default.');
            }
            $lock = 'builderlog-transfer:'.substr(hash('sha256', strtolower($target['db'])), 0, 40);
            if (! $dryRun) {
                $locked = $pdo->query("SELECT GET_LOCK('$lock', 0)")->fetchColumn() === '1';
                if (! $locked) {
                    throw new RuntimeException('Another restore holds the destination lock.');
                }
            }
            $this->connections->assertEmpty($pdo);

            $report = $this->summary($manifest, $alias, $target) + [
                'export_manifest_sha256' => hash_file('sha256', $path.'/manifest.json'),
            ];
            if ($dryRun) {
                return $report + [
                    'dry_run' => true,
                    'deferred' => ['Actual engine DDL compatibility, target SQL constraints, native value round trip and auto-increment validation.'],
                ];
            }

            return $this->install($connection, $pdo, $path, $manifest, $reference, $report, $stage);
        } finally {
            if ($locked) {
                $pdo->query("SELECT RELEASE_LOCK('$lock')");
            }
            DB::purge(ClientDatabaseConnection::DESTINATION);
            config(['database.connections.'.ClientDatabaseConnection::DESTINATION => null]);
        }
    }

    public function summary(array $manifest, string $alias, array $target): array
    {
        $counts = array_map(fn ($entry) => $entry['count'], $manifest['tables']);

        return [
            'client' => $manifest['client'], 'export' => $manifest['id'], 'destination' => $alias,
            'destination_engine_version' => $target['version'],
            'destination_identity' => $target,
            'source_counts' => $counts, 'historical_exceptions' => $manifest['historical_exceptions'],
            'existing_rows_to_restore' => array_sum(array_intersect_key($counts, array_flip($manifest['order']))),
            'workers_using_default' => isset($manifest['schema']['workers']['columns']['display_order']) ? 0 : $counts['workers'],
            'memberships_to_generate' => $manifest['memberships_to_generate'],
        ];
    }

    public function install(Connection $connection, PDO $pdo, string $path, array $manifest, array $reference, array $report, Closure $stage): array
    {
        $this->connections->assertEmpty($pdo);
        $stage('destination migrations');
        $this->migrate($connection, $pdo);
        $stage('destination schema');
        $schema = $this->validator->schema($pdo);
        $this->validator->compatible($schema, $reference, false);
        $history = $pdo->query('SELECT migration FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        if ($history !== array_keys($reference['migrations'])) {
            throw new RuntimeException('Destination migration history differs from the reviewed reference.');
        }
        $this->assertConstraintChecks($pdo);
        $this->initializeCounters($pdo, $manifest);

        $expected = [];
        $generated = 0;
        $pdo->beginTransaction();
        try {
            foreach (ClientDatabaseValidator::ORDER as $table) {
                $stage('import '.$table);
                [$expected[$table], $created] = $this->importTable($pdo, $path, $manifest, $schema[$table], $table);
                $generated += $created;
            }
            $stage('transactional data validation');
            if ($generated !== $manifest['memberships_to_generate']) {
                throw new RuntimeException('Generated membership count differs from preflight.');
            }
            foreach ($expected as $table => [$columns, $count, $sha]) {
                $this->verifyRows($pdo, $table, $schema[$table], $columns, $count, $sha);
            }
            $this->validator->checkForeignKeys($pdo, $schema);
            if ($this->validator->exceptions($pdo) !== $manifest['historical_exceptions']) {
                throw new RuntimeException('Historical exception profile differs from the source.');
            }
            foreach (ClientDatabaseValidator::EXCLUDED as $table) {
                if ($table !== 'migrations' && $pdo->query('SELECT 1 FROM '.$this->validator->id($table).' LIMIT 1')->fetchColumn()) {
                    throw new RuntimeException("Excluded destination table is not empty: $table.");
                }
            }
            $pdo->commit();
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        $stage('auto-increment and final verification');
        $this->verifyCounters($pdo, $manifest, $schema);
        $this->validator->compatible($this->validator->schema($pdo), $reference, false);
        $destinationCounts = array_fill_keys(ClientDatabaseValidator::EXCLUDED, 0);
        $destinationCounts['migrations'] = count($history);
        foreach ($expected as $table => [, $count]) {
            $destinationCounts[$table] = $count;
        }
        ksort($destinationCounts);
        $report += [
            'dry_run' => false, 'destination_counts' => $destinationCounts,
            'memberships_generated' => $generated, 'validated_migrations' => count($history),
            'validated_business_tables' => count($expected),
            'validation' => 'schema, migration history, ordered primary keys and preserved values, foreign keys, historical exceptions and auto-increment passed',
        ];
        $stage('writing validation report');
        $this->writeReport($path, $report);

        return $report;
    }

    private function migrate(Connection $connection, PDO $pdo): void
    {
        // The reviewed migrations use unqualified Schema and DB calls. Laravel temporarily
        // resolves those through --database and restores the process default on return.
        // Shield other configured connections throughout the nested Artisan invocation.
        $connections = config('database.connections');
        $name = ClientDatabaseConnection::DESTINATION;
        config(['database.connections' => [$name => $connections[$name]]]);
        try {
            if ($connection->getPdo() !== $pdo
                || Artisan::call('migrate', ['--database' => $name, '--force' => true, '--no-interaction' => true]) !== 0
                || $connection->getPdo() !== $pdo) {
                throw new RuntimeException('Migration command failed or connection changed.');
            }
        } finally {
            config(['database.connections' => $connections]);
        }
    }

    private function assertConstraintChecks(PDO $pdo): void
    {
        $settings = $pdo->query('SELECT @@foreign_key_checks AS fk, @@unique_checks AS uq, @@sql_mode AS mode')->fetch(PDO::FETCH_ASSOC);
        if ($settings['fk'] !== '1' || $settings['uq'] !== '1' || ! str_contains($settings['mode'], 'STRICT_')) {
            throw new RuntimeException('Destination check constraints and strict SQL mode must be active.');
        }
    }

    public function package(string $path, string $client, string $export, array $reference): array
    {
        if (! is_file($path.'/manifest.json') || is_link($path) || is_link($path.'/manifest.json')) {
            throw new RuntimeException('Export package manifest is missing.');
        }
        $manifest = json_decode(file_get_contents($path.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 1 || ($manifest['complete'] ?? null) !== true
            || ($manifest['client'] ?? null) !== $client || ($manifest['id'] ?? null) !== $export
            || ($manifest['migration_reference'] ?? null) !== hash('sha256', serialize($reference['migrations']))) {
            throw new RuntimeException('Invalid, incomplete or incompatible export package.');
        }
        $this->validator->compatible($manifest['schema'], $reference, true);
        $order = array_values(array_intersect(ClientDatabaseValidator::ORDER, array_keys($manifest['schema'])));
        if ($order !== $manifest['order'] || array_keys($manifest['schema']) !== array_keys($manifest['tables'])) {
            throw new RuntimeException('Export table inventory or order is inconsistent.');
        }
        foreach ($manifest['tables'] as $table => $entry) {
            $policy = $entry['policy'];
            if (! is_int($entry['count']) || $entry['count'] < 0
                || ($entry['auto_increment'] !== null && ! preg_match('/\A[1-9][0-9]*\z/', $entry['auto_increment']))
                || (in_array($table, $order, true) !== ($policy === 'restore'))
                || (! in_array($policy, ['restore', 'exclude', 'archive'], true))
                || ($policy === 'archive' && ! in_array($table, ClientDatabaseValidator::OPERATIONAL, true))
                || ($policy === 'exclude' && in_array($table, ClientDatabaseValidator::OPERATIONAL, true) && $entry['count'] !== 0)) {
                throw new RuntimeException("Invalid package policy or count for $table.");
            }
            if ($policy === 'exclude') {
                continue;
            }

            $this->validateFile($path, $table, $manifest['schema'][$table]['columns'], $entry);
        }
        $manifest['memberships_to_generate'] = $this->validator->validateMemberships($path, $manifest['schema']);

        return $manifest;
    }

    private function validateFile(string $path, string $table, array $columns, array $entry): void
    {
        $file = $path.'/'.$table.'.jsonl';
        if (! is_file($file) || is_link($file) || hash_file('sha256', $file) !== $entry['sha256']) {
            throw new RuntimeException("Missing or corrupt export file: $table.");
        }
        $count = iterator_count($this->validator->rowsFromFile($path, $table, $columns));
        if ($count !== $entry['count']) {
            throw new RuntimeException("Incorrect export row count: $table.");
        }
    }

    private function importTable(PDO $pdo, string $path, array $manifest, array $target, string $table): array
    {
        $targetColumns = array_keys($target['columns']);
        $source = $manifest['schema'][$table]['columns'] ?? null;
        $digest = hash_init('sha256');
        $sourceDigest = hash_init('sha256');
        $count = 0;
        if ($source !== null) {
            $keys = array_values(array_intersect($targetColumns, array_keys($source)));
            $batch = [];
            $bytes = 0;
            foreach ($this->validator->rowsFromFile($path, $table, $source) as $row) {
                $line = $this->validator->line($row);
                if ($batch !== [] && (count($batch) >= 100 || $bytes + strlen($line) > 262144)) {
                    $this->insertStatement($pdo, $table, $keys, count($batch))->execute(array_merge(...$batch));
                    $batch = [];
                    $bytes = 0;
                }
                hash_update($sourceDigest, $line);
                $batch[] = array_map(fn ($key) => $row[$key], $keys);
                $bytes += strlen($line);
                if ($table === 'workers' && ! isset($source['display_order'])) {
                    $row['display_order'] = '0';
                }
                $projected = [];
                foreach ($targetColumns as $key) {
                    $projected[$key] = $row[$key];
                }
                hash_update($digest, $this->validator->line($projected));
                $count++;
            }
            if ($batch !== []) {
                $this->insertStatement($pdo, $table, $keys, count($batch))->execute(array_merge(...$batch));
            }
            if ($count !== $manifest['tables'][$table]['count'] || hash_final($sourceDigest) !== $manifest['tables'][$table]['sha256']) {
                throw new RuntimeException("Export contents changed during import: $table.");
            }
        }

        $generated = 0;
        $dailyColumns = $manifest['schema']['daily_logs']['columns'];
        if ($table === 'daily_log_site_managers' && isset($dailyColumns['site_manager_id'])) {
            $exists = $pdo->prepare('SELECT 1 FROM daily_log_site_managers WHERE daily_log_id=? AND site_manager_id=?');
            $fields = ['daily_log_id', 'site_manager_id', 'created_at', 'updated_at'];
            $insert = $this->insertStatement($pdo, $table, $fields);
            $dailyDigest = hash_init('sha256');
            foreach ($this->validator->rowsFromFile($path, 'daily_logs', $dailyColumns) as $row) {
                hash_update($dailyDigest, $this->validator->line($row));
                $pair = [$row['id'], $row['site_manager_id']];
                $exists->execute($pair);
                $present = $exists->fetchColumn();
                $exists->closeCursor();
                if ($present) {
                    continue;
                }
                $values = [...$pair, $row['created_at'], $row['created_at']];
                $insert->execute($values);
                $projected = ['id' => $pdo->lastInsertId()] + array_combine($fields, $values);
                hash_update($digest, $this->validator->line($projected));
                $generated++;
            }
            if (hash_final($dailyDigest) !== $manifest['tables']['daily_logs']['sha256']) {
                throw new RuntimeException('Daily log export changed during membership generation.');
            }
        }

        return [[$targetColumns, $count + $generated, hash_final($digest)], $generated];
    }

    private function insertStatement(PDO $pdo, string $table, array $keys, int $rows = 1): \PDOStatement
    {
        $columns = implode(',', array_map($this->validator->id(...), $keys));
        $placeholder = implode(',', array_fill(0, count($keys), '?'));

        $values = implode(',', array_fill(0, $rows, "($placeholder)"));

        return $pdo->prepare('INSERT INTO '.$this->validator->id($table)." ($columns) VALUES $values");
    }

    private function verifyRows(PDO $pdo, string $table, array $definition, array $columns, int $expectedCount, string $expectedDigest): void
    {
        $digest = hash_init('sha256');
        $count = 0;
        foreach ($this->validator->rows($pdo, $table, $definition, $columns) as $row) {
            hash_update($digest, $this->validator->line($row));
            $count++;
        }
        if ($count !== $expectedCount || hash_final($digest) !== $expectedDigest) {
            throw new RuntimeException("Restored values or primary keys differ: $table.");
        }
    }

    private function initializeCounters(PDO $pdo, array $manifest): void
    {
        $counters = $this->validator->counters($pdo);
        foreach (ClientDatabaseValidator::ORDER as $table) {
            $next = $manifest['tables'][$table]['auto_increment'] ?? null;
            if ($next === null) {
                continue;
            }
            // InnoDB can expose an uninitialized counter as NULL for a new empty table.
            if ($this->less($counters[$table] ?? '1', $next)) {
                $pdo->exec('ALTER TABLE '.$this->validator->id($table).' AUTO_INCREMENT = '.$next);
            }
        }
    }

    private function verifyCounters(PDO $pdo, array $manifest, array $schema): void
    {
        $actual = $this->validator->counters($pdo);
        foreach (ClientDatabaseValidator::ORDER as $table) {
            $next = $manifest['tables'][$table]['auto_increment'] ?? null;
            if ($next !== null && $this->less($actual[$table] ?? '1', $next)) {
                throw new RuntimeException("Auto-increment counter could not be preserved on $table.");
            }
            foreach ($schema[$table]['columns'] as $column => [, , , $extra]) {
                if ($extra !== 'auto_increment') {
                    continue;
                }
                $max = $pdo->query('SELECT MAX('.$this->validator->id($column).') FROM '.$this->validator->id($table))->fetchColumn();
                if ($max !== null && (! isset($actual[$table]) || ! $this->less($max, $actual[$table]))) {
                    throw new RuntimeException("Auto-increment is not ahead of restored keys in $table.");
                }
            }
        }
    }

    private function less(string $left, string $right): bool
    {
        return strlen($left) !== strlen($right) ? strlen($left) < strlen($right) : strcmp($left, $right) < 0;
    }

    private function writeReport(string $path, array $report): void
    {
        $file = $path.'/restore-'.Str::uuid().'.json';
        $handle = fopen($file, 'xb');
        $json = json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
        if ($handle === false) {
            throw new RuntimeException('Cannot open restore report.');
        }
        try {
            if (! chmod($file, 0600) || fwrite($handle, $json) !== strlen($json) || ! fflush($handle) || ! fsync($handle)) {
                throw new RuntimeException('Cannot finish restore report.');
            }
        } finally {
            fclose($handle);
        }
    }
}
