<?php
return [
    'default' => getenv('DB_CONNECTION') ?: 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'driver_type' => 'mysql',
            'host'        => getenv('DB_HOST') ?: '127.0.0.1',
            'port'        => getenv('DB_PORT') ?: 3306,
            'database'    => getenv('DB_DATABASE') ?: 'email_system',
            'username'    => getenv('DB_USERNAME') ?: 'root',
            'password'    => getenv('DB_PASSWORD') ?: '123456',
            'unix_socket' => '',
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options'     => [
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        ],
        'kingbase' => [
            'driver'      => 'pgsql',
            'driver_type' => 'kingbase',
            'host'        => getenv('KB_HOST') ?: '127.0.0.1',
            'port'        => getenv('KB_PORT') ?: 54321,
            'database'    => getenv('KB_DATABASE') ?: 'email_system_kb',
            'username'    => getenv('KB_USERNAME') ?: 'SYSTEM',
            'password'    => getenv('KB_PASSWORD') ?: '123456',
            'charset'     => 'utf8',
            'prefix'      => '',
            'schema'      => 'public',
            'sslmode'     => 'prefer',
        ],
    ],
];
