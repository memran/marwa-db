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
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, title TEXT, created_at TEXT, updated_at TEXT)");

class User extends Model {
    protected static array $fillable = ['name'];
    public function posts() { return $this->hasMany(Post::class, 'user_id'); }
}
class Post extends Model {
    protected static array $fillable = ['user_id', 'title'];
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}

$user = User::create(['name' => 'Bob']);
Post::create(['user_id' => $user->id, 'title' => 'First Post']);

printf("User: %s\nPosts: %d\n", $user->name, count($user->posts));

$user->load('posts');
foreach ($user->getRelation('posts') as $post) {
    printf("  - %s\n", $post->title);
}

$post = Post::with('user')->first();
printf("Post author: %s\n", $post->user->name ?? 'none');
