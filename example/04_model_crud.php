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
    protected $fillable = ['name', 'email'];
}

$cm->getPdo()->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)");

// Create
$user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);

// Read
$found = User::find($user->id);
print_r($found->toArray());

// Update
$found->email = 'alice@new.com';
$found->save();

// Delete
$found->delete();
