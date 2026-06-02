<?php
require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Schema\MigrationRepository;
use Marwa\DB\Schema\Schema;

$config = new Config([
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => __DIR__ . '/../storage/example.sqlite',
        ],
    ],
]);

$cm = new ConnectionManager($config);

if (!is_dir(__DIR__ . '/../storage')) {
    mkdir(__DIR__ . '/../storage', 0777, true);
}

Schema::init($cm, 'sqlite');

$repo = new MigrationRepository($cm->getPdo('sqlite'), __DIR__ . '/../database/migrations');
$count = $repo->migrate();

echo "Migrations applied: {$count}\n";
