<?php

namespace App\Services\Database;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class ClientDatabaseRebuildService
{
    public function __construct(
        private ClientDatabaseConnection $connections,
        private ClientDatabaseValidator $validator,
        private ClientDatabaseRestoreService $restore,
        private ClientDatabaseRebuildBackup $backups,
    ) {}

    public function run(array $options, Closure $stage): array
    {
        $stage('rebuild options');
        $client = $options['client'];
        $alias = $options['source'];
        $settings = $this->connections->settings($client, 'source', $alias);
        $dry = $options['dry-run'];
        if (! $dry && ($options['confirm-database'] !== $settings['database'] || ! $options['writes-stopped'])) {
            throw new RuntimeException('Execution requires --confirm-database with the exact database name and --writes-stopped.');
        }
        foreach (['export', 'backup', 'rehearsal'] as $id) {
            if (! Str::isUuid((string) $options[$id])) {
                throw new RuntimeException("Select a valid UUID for --$id.");
            }
        }

        $root = rtrim(config('client_database.storage'), '/').'/'.$client;
        $path = $root.'/'.$options['export'];
        $workspace = $root.'/rebuild-'.$options['backup'];
        $sql = $workspace.'/legacy.sql';
        $planFile = $workspace.'/verified-plan.json';

        $stage('rollback backup');
        if (! is_dir($workspace) || is_link($workspace) || ! is_file($sql) || is_link($sql)
            || (fileperms($workspace) & 0077) || (fileperms($sql) & 0077) || filesize($sql) === 0) {
            throw new RuntimeException('Private rollback SQL backup is missing or has unsafe permissions.');
        }
        $space = disk_free_space($workspace);
        if ($space === false || $space < filesize($sql)) {
            throw new RuntimeException('Not enough private workspace space for verification.');
        }
        $backupHash = hash_file('sha256', $sql);
        if ($backupHash === false) {
            throw new RuntimeException('Cannot read or checksum the rollback SQL backup.');
        }

        $stage('export verification');
        $reference = $this->validator->reference();
        $manifest = $this->restore->package($path, $client, $options['export'], $reference);
        if ($manifest['source']['alias'] !== $alias || (strtotime($manifest['timestamp']) ?: 0) < filemtime($sql)) {
            throw new RuntimeException('Export source differs or export predates the SQL backup.');
        }
        $manifestHash = hash_file('sha256', $path.'/manifest.json');
        if ($manifestHash === false) {
            throw new RuntimeException('Cannot checksum the export manifest.');
        }
        $rehearsalFile = $path.'/restore-'.$options['rehearsal'].'.json';
        $rehearsal = $this->readJson($rehearsalFile, 'Same-version migration rehearsal report is missing.');
        $stage('target connection');
        // The rebuild uses only the explicitly mapped source. The ordinary restore mapping is bypassed here alone.
        $connection = $dry ? $this->connections->source($settings) : $this->connections->destination($settings);
        $pdo = null;
        $locked = false;
        try {
            $pdo = $connection->getPdo();
            $target = $this->connections->identity($pdo, $settings);
            $targetVersion = $this->backups->version($target);
            if ($manifest['source']['identity'] !== $target) {
                throw new RuntimeException('Actual target identity differs from the export.');
            }
            if (($rehearsal['dry_run'] ?? null) !== false || ($rehearsal['export'] ?? null) !== $manifest['id']
                || ($rehearsal['client'] ?? null) !== $client
                || ($rehearsal['export_manifest_sha256'] ?? null) !== $manifestHash
                || ($rehearsal['validated_migrations'] ?? null) !== count($reference['migrations'])
                || ($rehearsal['mode'] ?? 'restore') !== 'restore'
                || ! str_starts_with($rehearsal['destination_identity']['db'] ?? '', 'bl_rebuild_rehearsal_')
                || ($rehearsal['destination_identity']['server_uuid'] ?? null) === $target['server_uuid']
                || $this->backups->version($rehearsal['destination_identity'] ?? []) !== $targetVersion) {
                throw new RuntimeException('The rehearsal did not verify this export on an independent MySQL server of the same version.');
            }

            $stage('privileges and TLS inspection');
            $this->checkPrivileges($pdo, $target['db']);
            $tls = $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN ('Ssl_cipher','Ssl_version')")->fetchAll(PDO::FETCH_KEY_PAIR);
            $lock = 'builderlog-transfer:'.substr(hash('sha256', strtolower($target['db'])), 0, 40);
            if (! $dry) {
                $locked = $pdo->query("SELECT GET_LOCK('$lock', 0)")->fetchColumn() === '1';
                if (! $locked) {
                    throw new RuntimeException('Another restore or rebuild holds the target lock.');
                }
            }

            $stage('complete legacy snapshot comparison');
            $snapshot = $this->backups->snapshot($pdo);
            if ($snapshot['schema'] !== $manifest['schema']) {
                throw new RuntimeException('Current schema differs from the fresh export.');
            }
            foreach ($manifest['tables'] as $table => $entry) {
                $actual = $snapshot['tables'][$table];
                if ($actual['count'] !== $entry['count'] || ($entry['sha256'] !== null && $actual['sha256'] !== $entry['sha256'])
                    || ($entry['auto_increment'] !== null && $actual['auto_increment'] !== $entry['auto_increment'])) {
                    throw new RuntimeException("Legacy data changed after export: $table.");
                }
            }
            $proof = [
                'target' => $target, 'backup' => $options['backup'], 'backup_sha256' => $backupHash,
                'manifest_sha256' => $manifestHash, 'rehearsal_sha256' => hash_file('sha256', $rehearsalFile),
                'legacy_snapshot_sha256' => hash('sha256', serialize($snapshot)),
            ];
            $report = $this->restore->summary($manifest, $alias, $target) + [
                'mode' => 'rebuild', 'sql_backup' => $sql, 'sql_sha256' => $backupHash, 'application_schema_tables' => count($snapshot['tables']),
                'target_database_defaults' => $snapshot['database_defaults'],
                'target_engine' => 'MySQL 8 / InnoDB', 'tls' => $tls, 'tls_ca_configured' => ! empty($settings['ssl_ca']),
                'privileges' => 'Required database-scoped rebuild privileges verified from SHOW GRANTS.',
                'write_freeze_attested' => $options['writes-stopped'],
                'expected_destination_tables' => count($reference['tables']),
                'expected_migrations' => count($reference['migrations']),
                'transformations' => ['Legacy daily log manager relationships become pivot memberships.', 'Absent worker display_order uses database default 0.'],
            ];
            if ($dry) {
                $stage('independent local SQL rollback verification');
                $verifier = $this->backups->verify($sql, $backupHash, $target, $snapshot);
                if ($verifier['server_uuid'] !== $rehearsal['destination_identity']['server_uuid']) {
                    throw new RuntimeException('The backup verifier and migration rehearsal must share the independent local server.');
                }
                $stage('writing rebuild plan');
                $this->writePlan($planFile, $proof, $verifier);

                return $report + [
                    'dry_run' => true, 'rollback_verification' => 'Full SQL loaded locally and matched schema, row digests, counts and counters including operational tables.',
                    'migration_rehearsal' => 'Standard migrate and full restore passed on the same numeric server version.',
                    'checkpoint' => 'Explicit database confirmation and write freeze attestation are required before execution.',
                ];
            }

            $stage('confirmed plan verification');
            $plan = $this->readJson($planFile, 'Run a successful rebuild dry-run before execution.');
            if (($plan['proof'] ?? null) !== $proof
                || hash_file('sha256', $sql) !== $backupHash
                || hash_file('sha256', $path.'/manifest.json') !== $manifestHash) {
                throw new RuntimeException('Target, backup, export or rehearsal differs from the verified dry-run. Start a new plan.');
            }

            $stage('remove verified legacy application tables');
            $tables = implode(', ', array_map($this->validator->id(...), array_keys($snapshot['tables'])));
            $pdo->exec('SET SESSION foreign_key_checks=0');
            try {
                $pdo->exec("DROP TABLE $tables");
            } finally {
                $pdo->exec('SET SESSION foreign_key_checks=1');
            }

            return $this->restore->install($connection, $pdo, $path, $manifest, $reference, $report, $stage);
        } finally {
            if ($locked) {
                $pdo->query("SELECT RELEASE_LOCK('$lock')");
            }
            if ($dry) {
                $connection->disconnect();
            } else {
                DB::purge(ClientDatabaseConnection::DESTINATION);
                config(['database.connections.'.ClientDatabaseConnection::DESTINATION => null]);
            }
        }
    }

    private function checkPrivileges(PDO $pdo, string $database): void
    {
        $required = ['SELECT', 'INSERT', 'UPDATE', 'CREATE', 'ALTER', 'DROP', 'INDEX', 'REFERENCES', 'SHOW VIEW', 'TRIGGER', 'EVENT', 'CREATE ROUTINE', 'ALTER ROUTINE'];
        $granted = [];
        $all = false;
        foreach ($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $grant) {
            if (str_starts_with($grant, 'REVOKE ')) {
                throw new RuntimeException('Partial privilege revocations require manual review.');
            }
            if (! preg_match('/\AGRANT (.+?) ON (.+?) TO /', $grant, $parts)) {
                continue;
            }
            $scope = str_replace(['\\_', '\\%'], ['_', '%'], $parts[2]);
            if ($scope !== '*.*' && $scope !== $this->validator->id($database).'.*') {
                continue;
            }
            $all = $all || $parts[1] === 'ALL PRIVILEGES';
            $granted = [...$granted, ...explode(', ', $parts[1])];
        }
        if (! $all && ($missing = array_diff($required, $granted))) {
            throw new RuntimeException('Missing database-wide privileges: '.implode(', ', $missing).'.');
        }
    }

    private function readJson(string $file, string $error): array
    {
        if (! is_file($file) || is_link($file) || (fileperms($file) & 0077)) {
            throw new RuntimeException($error);
        }

        return json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }

    private function writePlan(string $file, array $proof, array $verifier): void
    {
        $temporary = tempnam(dirname($file), '.rebuild-plan-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot allocate a private plan file.');
        }
        try {
            $json = json_encode(['proof' => $proof, 'sql_verifier' => $verifier, 'verified_at' => now('UTC')->toIso8601String()], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
            if (! chmod($temporary, 0600) || file_put_contents($temporary, $json, LOCK_EX) !== strlen($json) || ! rename($temporary, $file)) {
                throw new RuntimeException('Cannot store the rebuild verification plan.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
