<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
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
}

$alice = User::create(['name' => 'Alice']);
$bob = User::create(['name' => 'Bob']);

Post::create(['user_id' => $alice->getKey(), 'title' => 'One']);
Post::create(['user_id' => $alice->getKey(), 'title' => 'Two']);
Post::create(['user_id' => $bob->getKey(), 'title' => 'Three']);

$users = User::withCount('posts', 'posts as total_posts')
    ->orderBy('id')
    ->get();

foreach ($users as $user) {
    printf(
        "%s has %d posts (%d total_posts)\n",
        $user->getAttribute('name'),
        (int) $user->getAttribute('posts_count'),
        (int) $user->getAttribute('total_posts')
    );
}
