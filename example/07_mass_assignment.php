<?php
require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;

$config = new Config([
    'default' => 'sqlite',
    'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']],
]);

$cm = new ConnectionManager($config);
Model::setConnectionManager($cm, 'sqlite');

$pdo = $cm->getPdo('sqlite');
$pdo->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    role TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
)");

class User extends Model {
    protected static array $fillable = ['name'];
    protected static array $guarded = ['role'];
}

$user = User::create(['name' => 'David', 'role' => 'admin']); // role ignored
print_r($user->toArray());
