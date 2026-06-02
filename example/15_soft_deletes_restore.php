<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use Marwa\DB\ORM\Traits\SoftDeletes;

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
$pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, deleted_at TEXT NULL)');

final class Post extends Model
{
    use SoftDeletes;

    protected static ?string $table = 'posts';

    protected static array $fillable = ['title'];
}

$post = Post::create(['title' => 'Draft post']);
$post->delete();

echo "Default query:\n";
print_r(Post::query()->get());

echo "With trashed:\n";
print_r(Post::withTrashed()->get());

$trashed = Post::onlyTrashed()->first();
if ($trashed !== null) {
    $trashed->restore();
}

echo "After restore:\n";
print_r(Post::query()->get());
