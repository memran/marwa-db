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

// Define a model
class User extends Model {
    protected static array $fillable = ['name', 'email'];
}

$pdo = $cm->getPdo('sqlite');
$pdo->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
)");

// Create
$user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);

// Read
$found = User::find($user->id);
print_r($found->toArray());

// Update
$found->setAttribute('email', 'alice@new.com');
$found->save();

// Delete
$found->delete();
