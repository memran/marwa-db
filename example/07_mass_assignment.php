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

$pdo = $cm->getPdo();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT)");

class User extends Model {
    protected $fillable = ['name'];
    protected $guarded = ['role'];
}

$user = User::create(['name' => 'David', 'role' => 'admin']); // role ignored
print_r($user->toArray());
