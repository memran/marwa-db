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

$pdo = $cm->getPdo();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT)");

class User extends Model {
    use SoftDeletes;
    protected $fillable = ['name'];
}

$user = User::create(['name' => 'Charlie']);
$user->delete();

print_r(User::all()->toArray()); // Empty because soft deleted
print_r(User::withTrashed()->get()->toArray()); // Shows deleted
