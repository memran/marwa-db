<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\ORM;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use Marwa\DB\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class LaravelHelpersTest extends TestCase
{
    public function testWhenAndUnlessConditionallyModifyTheQuery(): void
    {
        $manager = $this->makeManager();
        $this->seedData($manager);

        HelperUser::setConnectionManager($manager);
        HelperPost::setConnectionManager($manager);

        $user = HelperUser::query()
            ->when(true, static fn (QueryBuilder $query): QueryBuilder => $query->where('active', '=', 1))
            ->unless(false, static fn (QueryBuilder $query): QueryBuilder => $query->where('name', '=', 'Bob'))
            ->first();

        self::assertInstanceOf(HelperUser::class, $user);
        self::assertSame('Bob', $user?->getAttribute('name'));

        $fallback = HelperUser::query()
            ->when(false, static fn (QueryBuilder $query): QueryBuilder => $query->where('name', '=', 'Nobody'), static fn (QueryBuilder $query): QueryBuilder => $query->where('name', '=', 'Alice'))
            ->first();

        self::assertInstanceOf(HelperUser::class, $fallback);
        self::assertSame('Alice', $fallback?->getAttribute('name'));
    }

    public function testWhereKeySupportsScalarAndArrayLookups(): void
    {
        $manager = $this->makeManager();
        $this->seedData($manager);

        HelperUser::setConnectionManager($manager);
        HelperPost::setConnectionManager($manager);

        $single = HelperUser::whereKey(1)->first();
        self::assertInstanceOf(HelperUser::class, $single);
        self::assertSame('Alice', $single?->getAttribute('name'));

        $many = HelperUser::whereKey([1, 2])->orderBy('id')->get();
        self::assertCount(2, $many);
        self::assertSame(['Alice', 'Bob'], array_map(
            static fn (HelperUser $user): string => (string) $user->getAttribute('name'),
            $many
        ));
    }

    public function testNewQueryReturnsAnORMQueryBuilder(): void
    {
        $manager = $this->makeManager();
        $this->seedData($manager);

        HelperUser::setConnectionManager($manager);
        HelperPost::setConnectionManager($manager);

        $query = HelperUser::newQuery()->where('name', '=', 'Alice');

        self::assertInstanceOf(QueryBuilder::class, $query);
        self::assertSame('Alice', $query->first()?->getAttribute('name'));
    }

    public function testWithCountAddsDefaultAndAliasedCountAttributes(): void
    {
        $manager = $this->makeManager();
        $this->seedData($manager);

        HelperUser::setConnectionManager($manager);
        HelperPost::setConnectionManager($manager);

        $users = HelperUser::query()
            ->withCount('posts', 'posts as total_posts')
            ->orderBy('id')
            ->get();

        self::assertCount(2, $users);
        self::assertSame(1, $users[0]?->getAttribute('posts_count'));
        self::assertSame(1, $users[0]?->getAttribute('total_posts'));
        self::assertSame(2, $users[1]?->getAttribute('posts_count'));
        self::assertSame(2, $users[1]?->getAttribute('total_posts'));
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
        $pdo->exec('CREATE TABLE helper_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE helper_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL)');
        $pdo->exec("INSERT INTO helper_users (name, active) VALUES ('Alice', 1)");
        $pdo->exec("INSERT INTO helper_users (name, active) VALUES ('Bob', 1)");
        $pdo->exec("INSERT INTO helper_posts (user_id, title) VALUES (1, 'A1')");
        $pdo->exec("INSERT INTO helper_posts (user_id, title) VALUES (2, 'B1')");
        $pdo->exec("INSERT INTO helper_posts (user_id, title) VALUES (2, 'B2')");
    }
}

final class HelperUser extends Model
{
    protected static ?string $table = 'helper_users';

    public function posts(): \Marwa\DB\ORM\Relations\HasMany
    {
        return $this->hasMany(HelperPost::class, 'user_id');
    }
}

final class HelperPost extends Model
{
    protected static ?string $table = 'helper_posts';
}
