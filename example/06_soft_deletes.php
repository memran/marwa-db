<?php
require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use Marwa\DB\ORM\Traits\SoftDeletes;

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
    created_at TEXT NULL,
    updated_at TEXT NULL,
    deleted_at TEXT NULL
)");

class User extends Model {
    use SoftDeletes;
    protected static array $fillable = ['name'];
}

$user = User::create(['name' => 'Charlie']);
$user->delete();

print_r(User::all()); // Empty because soft deleted
print_r(User::withTrashed()->get()); // Shows deleted
