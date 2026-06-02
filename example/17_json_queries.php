<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Facades\DB;

$config = new Config([
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ],
]);

$cm = new ConnectionManager($config);
DB::setManager($cm);

$pdo = $cm->getPdo('sqlite');
$pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, meta TEXT NOT NULL)');
$pdo->exec("INSERT INTO products (name, meta) VALUES ('Phone', '{\"featured\":true,\"tags\":[\"tech\",\"mobile\"]}')");
$pdo->exec("INSERT INTO products (name, meta) VALUES ('Notebook', '{\"featured\":false,\"tags\":[\"office\",\"paper\"]}')");

$featured = DB::table('products', 'sqlite')
    ->whereJsonValue('meta', '$.featured', 1)
    ->get();

echo "Featured products:\n";
print_r($featured);

$tech = DB::table('products', 'sqlite')
    ->where('meta', 'LIKE', '%tech%')
    ->get();

echo "\nProducts tagged tech:\n";
print_r($tech);
