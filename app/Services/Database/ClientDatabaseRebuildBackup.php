<?php

namespace App\Services\Database;

use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

class ClientDatabaseRebuildBackup
{
    public function __construct(private ClientDatabaseConnection $connections, private ClientDatabaseValidator $validator) {}

    public function verify(string $file, string $sha, array $target, array $snapshot): array
    {
        $settings = config('client_database.rebuild_verifier');
        if (! preg_match('/\Abl_rebuild_verify_[a-z0-9_]+\z/', (string) $settings['database'])
            || ! filter_var($settings['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]])
            || $settings['host'] !== '127.0.0.1' || ! preg_match('/\A[a-zA-Z0-9_-]+\z/', (string) $settings['username'])) {
            throw new RuntimeException('Configure a new bl_rebuild_verify_* database on a separate loopback MySQL server.');
        }
        $connection = $this->connections->source($settings);
        try {
            $pdo = $connection->getPdo();
            $identity = $this->connections->identity($pdo, $settings);
            if ($identity['server_uuid'] === null || $identity['server_uuid'] === $target['server_uuid']
                || $identity['db'] === $target['db'] || $this->version($identity) !== $this->version($target)) {
                throw new RuntimeException('SQL verification requires an independent local server with the same MySQL version.');
            }
            $this->connections->assertEmpty($pdo);
            $this->restoreSql($file, $settings);
            if (hash_file('sha256', $file) !== $sha || $this->snapshot($pdo) !== $snapshot) {
                throw new RuntimeException('Restored SQL backup differs from the current legacy database.');
            }

            return $identity;
        } finally {
            $connection->disconnect();
        }
    }

    private function restoreSql(string $file, array $settings): void
    {
        $defaults = tempnam(sys_get_temp_dir(), 'builderlog-mysql-');
        if ($defaults === false) {
            throw new RuntimeException('Could not allocate a private MySQL client options file.');
        }
        $input = null;
        try {
            $password = addcslashes((string) $settings['password'], "\\\"\n\r\t");
            $escapedPassword = strtr($password, ["\n" => 'n', "\r" => 'r', "\t" => 't']);
            $options = "[client]\npassword=\"$escapedPassword\"\n";
            if (! chmod($defaults, 0600) || file_put_contents($defaults, $options) !== strlen($options)) {
                throw new RuntimeException('Cannot write isolated MySQL client options.');
            }
            $input = fopen($file, 'rb');
            if ($input === false) {
                throw new RuntimeException('Cannot read SQL backup.');
            }
            $process = new Process([
                $settings['mysql'], '--defaults-file='.$defaults, '--protocol=TCP', '--host=127.0.0.1',
                '--port='.$settings['port'], '--user='.$settings['username'], '--database='.$settings['database'],
                '--binary-mode', '--local-infile=0',
            ], input: $input, timeout: null);
            // Dump statements may contain secrets. Never retain or print mysql stdout/stderr.
            $process->disableOutput();
            if ($process->run() !== 0) {
                throw new RuntimeException('MySQL failed to restore the rollback backup into the local verifier.');
            }
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            unlink($defaults);
        }
    }

    public function snapshot(PDO $pdo): array
    {
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        try {
            $schema = $this->validator->schema($pdo);
            $this->assertTableOnly($pdo, $schema);
            $tableStatus = $pdo->query('SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE()')->fetch(PDO::FETCH_NUM);
            $counters = $this->validator->counters($pdo);
            $entries = [];
            foreach ($schema as $table => $definition) {
                $ddl = $pdo->query('SHOW CREATE TABLE '.$this->validator->id($table))->fetch(PDO::FETCH_NUM)[1];
                $ddl = preg_replace('/(?<=\)) (ENGINE=[^\n]*?) AUTO_INCREMENT=\d+(?= )/', ' $1', $ddl);
                // MySQL can spell out a column charset after loading a dump even when the
                // effective charset and collation are identical to the original table.
                $ddl = preg_replace('/^(  `[^`\n]+` (?:(?:var)?char\(\d+\)|(?:tiny|medium|long)?text)) CHARACTER SET utf8mb4 (?=COLLATE utf8mb4_)/mi', '$1 ', $ddl);
                $hash = hash_init('sha256');
                $count = 0;
                foreach ($this->validator->rows($pdo, $table, $definition, array_keys($definition['columns'])) as $row) {
                    hash_update($hash, $this->validator->line($row));
                    $count++;
                }
                $hasCounter = in_array('auto_increment', array_column($definition['columns'], 3), true);
                $entries[$table] = [
                    'count' => $count,
                    'sha256' => hash_final($hash),
                    'auto_increment' => $hasCounter ? ($counters[$table] ?? '1') : null,
                    'ddl_sha256' => hash('sha256', $ddl),
                ];
            }
            if ($schema !== $this->validator->schema($pdo) || $counters !== $this->validator->counters($pdo)) {
                throw new RuntimeException('Legacy metadata changed during the full snapshot check.');
            }

            return ['schema' => $schema, 'database_defaults' => $tableStatus, 'tables' => $entries];
        } finally {
            $pdo->rollBack();
        }
    }

    private function assertTableOnly(PDO $pdo, array $schema): void
    {
        foreach (['ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA', 'TRIGGERS' => 'TRIGGER_SCHEMA'] as $kind => $column) {
            if ($pdo->query("SELECT 1 FROM information_schema.$kind WHERE $column=DATABASE() LIMIT 1")->fetchColumn()) {
                throw new RuntimeException("Unsupported application objects in $kind; no tables can be removed.");
            }
        }
        if (array_diff(array_column($schema, 'engine'), ['InnoDB'])
            || $pdo->query('SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE
                (TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA IS NOT NULL AND REFERENCED_TABLE_SCHEMA<>DATABASE())
                OR (TABLE_SCHEMA<>DATABASE() AND REFERENCED_TABLE_SCHEMA=DATABASE()) LIMIT 1')->fetchColumn()) {
            throw new RuntimeException('Non-InnoDB objects or cross-database foreign keys require separate review.');
        }
    }

    public function version(array $identity): string
    {
        if (($identity['server_uuid'] ?? null) === null || ! preg_match('/\A8\.\d+\.\d+/', $identity['version'] ?? '', $version)) {
            throw new RuntimeException('This rebuild is verified only for MySQL 8.');
        }

        return $version[0];
    }
}
