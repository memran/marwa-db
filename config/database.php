<?php

declare(strict_types=1);

/**
 * Marwa-DB example configuration.
 *
 * You can define multiple named connections here, e.g. 'default', 'read', 'replica1'.
 * Every connection supports:
 * - driver:     mysql (more drivers can be added in ConnectionFactory)
 * - host, port, database, username, password
 * - charset:    utf8mb4 is recommended
 * - options:    PDO options (associative array) — optional
 * - retry:      number of connection retry attempts
 * - retry_delay:milliseconds between retries
 * - debug:      when true, QueryLogger will record SQL + timings
 */

return [
    'default' => [
        'driver'      => 'mysql',
        'host'        => 'localhost',
        'port'        => 3306,
        'database'    => 'marwaphp',
        'username'    => 'root',
        'password'    => '',
        'charset'     => 'utf8mb4',
        'options'     => [
            // Example PDO options (optional; sensible defaults already applied)
            // \PDO::ATTR_PERSISTENT => true,
        ],
        'retry'       => 3,
        'retry_delay' => 300, // ms
        'debug'       => true,
    ],

    // Optional read/replicas:
    // 'read' => [
    //     'driver'      => 'mysql',
    //     'host'        => '10.0.0.10',
    //     'port'        => 3306,
    //     'database'    => 'app',
    //     'username'    => 'reader',
    //     'password'    => 'secret',
    //     'charset'     => 'utf8mb4',
    //     'retry'       => 2,
    //     'retry_delay' => 200,
    //     'debug'       => false,
    // ],
    //
    // 'replica1' => [ ... ],
    // 'replica2' => [ ... ],
];
