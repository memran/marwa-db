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
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, title TEXT)");

class User extends Model {
    public function posts() { return $this->hasMany(Post::class, 'user_id'); }
}
class Post extends Model {
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}

$user = User::create(['name' => 'Bob']);
Post::create(['user_id' => $user->id, 'title' => 'First Post']);

print_r($user->posts()->get()->toArray());
