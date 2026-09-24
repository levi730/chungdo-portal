<?php

return [

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        // Read-only diagnostic connection to production (MariaDB on kronos,
        // bound to 127.0.0.1 there). Reached through an SSH tunnel from the
        // Mac: `ssh -N -L 3307:127.0.0.1:3306 kronos`; the DDEV container sees
        // the Mac as host.docker.internal. The chungdo_ro user is granted
        // SELECT only, so this connection cannot mutate prod data.
        'prod_ro' => [
            'driver' => 'mysql',
            'host' => env('PROD_DB_HOST', 'host.docker.internal'),
            'port' => env('PROD_DB_PORT', '3307'),
            'database' => env('PROD_DB_DATABASE', 'chungdo'),
            'username' => env('PROD_DB_USERNAME', 'chungdo_ro'),
            'password' => env('PROD_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => false, // disable to preserve original behavior for existing applications
    ],

];
