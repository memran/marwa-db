<?php
require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Migrations\Migrator;

$config = new Config([
    'default' => 'sqlite',
    'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => __DIR__.'/app.sqlite']],
]);

$cm = new ConnectionManager($config);
$migrator = new Migrator($cm, __DIR__ . '/../database/migrations');

$migrator->migrate();

echo "Migrations applied.\n";
