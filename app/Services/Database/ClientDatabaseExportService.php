<?php

namespace App\Services\Database;

use Illuminate\Support\Str;
use RuntimeException;

class ClientDatabaseExportService
{
    public function __construct(private ClientDatabaseConnection $connections, private ClientDatabaseValidator $validator) {}

    public function export(string $client, string $alias, ?string $operational): array
    {
        $settings = $this->connections->settings($client, 'source', $alias);
        $reference = $this->validator->reference();
        if ($operational !== null && $operational !== 'archive') {
            throw new RuntimeException('Supported operational table policy: archive.');
        }
        $id = (string) Str::uuid();
        $root = rtrim(config('client_database.storage'), '/').'/'.$client;
        $path = $root.'/.'.$id.'.partial';
        if ((! is_dir($root) && ! mkdir($root, 0700, true)) || ! mkdir($path, 0700)) {
            throw new RuntimeException('Cannot create private export directory.');
        }

        $connection = $this->connections->source($settings);
        $pdo = $connection->getPdo();
        $identity = $this->connections->identity($pdo, $settings);
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        try {
            $schema = $this->validator->schema($pdo);
            $this->validator->compatible($schema, $reference, true);
            $this->validator->checkForeignKeys($pdo, $schema);
            $exceptions = $this->validator->exceptions($pdo);
            $counters = $this->validator->counters($pdo);
            $entries = [];
            foreach ($schema as $table => $definition) {
                $operationalTable = in_array($table, ClientDatabaseValidator::OPERATIONAL, true);
                $preserve = ! in_array($table, ClientDatabaseValidator::EXCLUDED, true);
                $archive = $operationalTable && $operational === 'archive';
                $count = 0;
                $hash = null;
                if ($preserve || $archive) {
                    $file = $path.'/'.$table.'.jsonl';
                    $handle = fopen($file, 'xb');
                    if ($handle === false || ! chmod($file, 0600)) {
                        throw new RuntimeException("Cannot open export for $table.");
                    }
                    try {
                        foreach ($this->validator->rows($pdo, $table, $definition, array_keys($definition['columns'])) as $row) {
                            $line = $this->validator->line($row);
                            if (fwrite($handle, $line) !== strlen($line)) {
                                throw new RuntimeException("Incomplete write for $table.");
                            }
                            $count++;
                        }
                        if (! fflush($handle) || ! fsync($handle)) {
                            throw new RuntimeException("Cannot flush export for $table.");
                        }
                    } finally {
                        fclose($handle);
                    }
                    $hash = hash_file('sha256', $file);
                    if ($hash === false) {
                        throw new RuntimeException("Cannot checksum $table.");
                    }
                } else {
                    $count = (int) $pdo->query('SELECT COUNT(*) FROM '.$this->validator->id($table))->fetchColumn();
                    if ($operationalTable && $count > 0) {
                        throw new RuntimeException("Non-empty $table needs an explicit --operational=archive policy.");
                    }
                }
                $entries[$table] = [
                    'policy' => $preserve ? 'restore' : ($archive ? 'archive' : 'exclude'),
                    'count' => $count,
                    'sha256' => $hash,
                    'auto_increment' => $counters[$table] ?? null,
                ];
            }
            if ($schema !== $this->validator->schema($pdo)) {
                throw new RuntimeException('Source schema changed during export.');
            }
        } finally {
            $pdo->rollBack();
            $connection->disconnect();
        }

        $manifest = [
            'format' => 1,
            'complete' => true,
            'id' => $id,
            'client' => $client,
            'source' => ['alias' => $alias, 'identity' => $identity],
            'timestamp' => now('UTC')->toIso8601String(),
            'application_revision' => hash('sha256', file_get_contents(base_path('composer.lock')).serialize($reference['migrations'])),
            'migration_reference' => hash('sha256', serialize($reference['migrations'])),
            'order' => array_values(array_intersect(ClientDatabaseValidator::ORDER, array_keys($schema))),
            'schema' => $schema,
            'tables' => $entries,
            'historical_exceptions' => $exceptions,
        ];
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
        $file = $path.'/manifest.json';
        if (file_put_contents($file, $json, LOCK_EX) !== strlen($json) || ! chmod($file, 0600) || ! rename($path, $root.'/'.$id)) {
            throw new RuntimeException('Cannot finalize the export package.');
        }

        return $manifest;
    }
}
