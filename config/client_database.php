<?php

return [
    'storage' => storage_path('app/private/client-backups'),

    'rebuild_verifier' => [
        'host' => '127.0.0.1',
        'port' => env('CLIENT_REBUILD_VERIFY_PORT'),
        'database' => env('CLIENT_REBUILD_VERIFY_DATABASE'),
        'username' => env('CLIENT_REBUILD_VERIFY_USERNAME'),
        'password' => env('CLIENT_REBUILD_VERIFY_PASSWORD'),
        'mysql' => env('CLIENT_REBUILD_MYSQL_CLIENT', 'mysql'),
    ],

    // Add one explicit entry per customer. These connections are only created by the transfer commands.
    'clients' => [
        'client1' => [
            'source' => [
                'name' => 'client1_legacy',
                'host' => env('LEGACY_DB_HOST'),
                'port' => env('LEGACY_DB_PORT', 3306),
                'database' => env('LEGACY_DB_DATABASE'),
                'username' => env('LEGACY_DB_USERNAME'),
                'password' => env('LEGACY_DB_PASSWORD'),
                'ssl_ca' => env('LEGACY_DB_SSL_CA'),
            ],
            'destination' => [
                'name' => 'client1_next',
                'host' => env('CLIENT1_NEXT_DB_HOST'),
                'port' => env('CLIENT1_NEXT_DB_PORT', 3306),
                'database' => env('CLIENT1_NEXT_DB_DATABASE'),
                'username' => env('CLIENT1_NEXT_DB_USERNAME'),
                'password' => env('CLIENT1_NEXT_DB_PASSWORD'),
                'ssl_ca' => env('CLIENT1_NEXT_DB_SSL_CA'),
            ],
        ],
    ],
];
