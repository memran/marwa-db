<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Facades\DB;
use Marwa\DB\Seeder\Seeder;
use Marwa\DB\Seeder\SeedRunner;

$config = new Config([
    'default' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ],
]);

$cm = new ConnectionManager($config);
DB::setManager($cm);

$pdo = $cm->getPdo();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

class UsersSeeder implements Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'SeederUser',
        ]);
    }
}

$runner = new SeedRunner($cm);
$runner->runOne(UsersSeeder::class, false);

print_r($pdo->query('SELECT * FROM users')->fetchAll(PDO::FETCH_ASSOC));
