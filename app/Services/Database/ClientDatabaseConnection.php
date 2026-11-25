<?php

namespace App\Services\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class ClientDatabaseConnection
{
    public const DESTINATION = '__client_transfer_destination';

    public function settings(string $client, string $role, string $alias): array
    {
        if (app()->configurationIsCached()) {
            throw new RuntimeException('Use uncached configuration for client database transfers.');
        }

        $clients = config('client_database.clients');
        $settings = $clients[$client][$role] ?? [];
        if (! preg_match('/\A[a-zA-Z0-9_-]+\z/', $client) || $alias === '' || ($settings['name'] ?? null) !== $alias) {
            throw new RuntimeException('Select a connection mapped to this client and role.');
        }

        foreach (['host', 'database', 'username'] as $key) {
            if (! is_string($settings[$key] ?? null) || ! preg_match('/\A[^;\x00-\x20]+\z/', $settings[$key])) {
                throw new RuntimeException("Invalid configured connection setting: $key.");
            }
        }
        if (! filter_var($settings['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
            throw new RuntimeException('Invalid configured database port.');
        }

        if ($role === 'destination') {
            foreach ($clients as $registeredClient => $mapping) {
                foreach ($mapping as $registeredRole => $other) {
                    if ($registeredClient === $client && $registeredRole === $role) {
                        continue;
                    }
                    if (strcasecmp($settings['database'], (string) ($other['database'] ?? '')) === 0) {
                        throw new RuntimeException('Destination database name is also mapped to a different transfer role.');
                    }
                }
            }
        }

        return $settings;
    }

    public function config(array $settings): array
    {
        $options = [PDO::ATTR_STRINGIFY_FETCHES => true, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 10];
        if (! empty($settings['ssl_ca'])) {
            $options[PDO\Mysql::ATTR_SSL_CA] = $settings['ssl_ca'];
            $options[PDO\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        return [
            ...array_intersect_key($settings, array_flip(['host', 'port', 'database', 'username', 'password'])),
            'driver' => 'mysql',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'timezone' => '+00:00',
            'strict' => true,
            'modes' => [
                'ONLY_FULL_GROUP_BY', 'STRICT_TRANS_TABLES', 'NO_ZERO_IN_DATE', 'NO_ZERO_DATE',
                'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION', 'NO_AUTO_VALUE_ON_ZERO',
            ],
            'prefix' => '',
            'options' => $options,
        ];
    }

    public function source(array $settings): Connection
    {
        return DB::build($this->config($settings));
    }

    public function destination(array $settings): Connection
    {
        if (config('database.default') === self::DESTINATION || config('database.connections.'.self::DESTINATION) !== null) {
            throw new RuntimeException('Reserved transfer connection name is already configured.');
        }

        config(['database.connections.'.self::DESTINATION => $this->config($settings)]);

        return DB::connection(self::DESTINATION);
    }

    public function identity(PDO $pdo, array $settings): array
    {
        $row = $pdo->query('SELECT DATABASE() AS db, @@hostname AS host, @@port AS port, @@server_id AS server_id, @@lower_case_table_names AS case_mode, VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
        if ($row['db'] !== $settings['database'] || $row['host'] === '') {
            throw new RuntimeException('Connected database identity differs from the explicit configuration.');
        }
        $row['server_uuid'] = $pdo->query("SHOW VARIABLES LIKE 'server_uuid'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? null;
        if ($row['server_uuid'] !== null) {
            $pdo->exec('SET SESSION information_schema_stats_expiry=0');
        }

        return $row;
    }

    public function assertDistinct(array $source, array $target): void
    {
        $sameName = $source['db'] === $target['db'] ||
            (($source['case_mode'] !== '0' || $target['case_mode'] !== '0') && strcasecmp($source['db'], $target['db']) === 0);
        $sameServer = ($source['server_uuid'] !== null && $source['server_uuid'] === $target['server_uuid'])
            || [$source['host'], $source['port'], $source['server_id']] === [$target['host'], $target['port'], $target['server_id']];
        if ($sameName && $sameServer) {
            throw new RuntimeException('Source and destination identify the same database.');
        }
    }

    public function assertEmpty(PDO $pdo): void
    {
        foreach (['TABLES' => 'TABLE_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA', 'TRIGGERS' => 'TRIGGER_SCHEMA'] as $kind => $column) {
            if ($pdo->query("SELECT 1 FROM information_schema.$kind WHERE $column = DATABASE() LIMIT 1")->fetchColumn()) {
                throw new RuntimeException("Destination is not empty ($kind).");
            }
        }
    }
}
