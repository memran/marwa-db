<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\ORM;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use Marwa\DB\ORM\Relations\BelongsTo;
use Marwa\DB\ORM\Relations\HasMany;
use PHPUnit\Framework\TestCase;

final class RelationInferenceTest extends TestCase
{
    public function testHasManyAndBelongsToCanInferTheRelatedClassFromTheRelationName(): void
    {
        $manager = $this->makeManager();
        $this->seedData($manager);

        User::setConnectionManager($manager);
        Post::setConnectionManager($manager);

        $user = User::query()
            ->with('posts')
            ->where('name', '=', 'Alice')
            ->first();

        self::assertInstanceOf(User::class, $user);
        $posts = $user?->getRelationValue('posts');
        self::assertIsArray($posts);
        self::assertCount(2, $posts);
        self::assertInstanceOf(Post::class, $posts[0] ?? null);

        $post = Post::query()
            ->where('title', '=', 'Welcome')
            ->first();

        self::assertInstanceOf(Post::class, $post);
        $owner = $post?->getRelationValue('user');
        self::assertInstanceOf(User::class, $owner);
        self::assertSame('Alice', $owner?->getAttribute('name'));
    }

    private function makeManager(): ConnectionManager
    {
        return new ConnectionManager(new Config([
            'default' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]));
    }

    private function seedData(ConnectionManager $manager): void
    {
        $pdo = $manager->getPdo();
        $pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL
            )'
        );
        $pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $pdo->exec("INSERT INTO users (name) VALUES ('Bob')");
        $pdo->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Welcome')");
        $pdo->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Second')");
        $pdo->exec("INSERT INTO posts (user_id, title) VALUES (2, 'Bob Post')");
    }
}

final class User extends Model
{
    protected static ?string $table = 'users';

    public function posts(): HasMany
    {
        return $this->hasMany('user_id');
    }
}

final class Post extends Model
{
    protected static ?string $table = 'posts';

    public function user(): BelongsTo
    {
        return $this->belongsTo('user_id');
    }
}
