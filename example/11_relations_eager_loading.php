<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use Marwa\DB\ORM\Relations\BelongsTo;
use Marwa\DB\ORM\Relations\HasMany;

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
Model::setConnectionManager($cm, 'sqlite');

$pdo = $cm->getPdo('sqlite');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL)');

final class User extends Model
{
    protected static ?string $table = 'users';

    protected static array $fillable = ['name'];

    public function posts(): HasMany
    {
        return $this->hasMany('user_id');
    }
}

final class Post extends Model
{
    protected static ?string $table = 'posts';

    protected static array $fillable = ['user_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo('user_id');
    }
}

$alice = User::create(['name' => 'Alice']);
$bob = User::create(['name' => 'Bob']);

Post::create(['user_id' => $alice->getKey(), 'title' => 'Welcome']);
Post::create(['user_id' => $alice->getKey(), 'title' => 'Second post']);
Post::create(['user_id' => $bob->getKey(), 'title' => 'Bob update']);

$users = User::with('posts')
    ->orderBy('id')
    ->get();

foreach ($users as $user) {
    $posts = $user->getRelationValue('posts');
    printf("%s has %d post(s)\n", $user->getAttribute('name'), is_array($posts) ? count($posts) : 0);

    foreach ($posts as $post) {
        printf("  - %s\n", $post->getAttribute('title'));
    }
}

$post = Post::with('user')
    ->where('title', '=', 'Welcome')
    ->first();

if ($post !== null) {
    $author = $post->getRelationValue('user');
    printf(
        "Post '%s' belongs to %s\n",
        $post->getAttribute('title'),
        $author?->getAttribute('name') ?? 'unknown'
    );
}
