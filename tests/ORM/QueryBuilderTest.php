<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\ORM;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use PDO;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        OrmUser::setConnectionManager($this->makeManager());
    }

    public function testModelQueryUsesResolvedTableName(): void
    {
        $manager = $this->makeManager();
        $pdo = $manager->getPdo();
        $pdo->exec('CREATE TABLE orm_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec("INSERT INTO orm_users (name) VALUES ('Alice')");

        OrmUser::setConnectionManager($manager);

        $result = OrmUser::query()->where('name', '=', 'Alice')->first();

        self::assertInstanceOf(OrmUser::class, $result);
        self::assertSame('Alice', $result?->getAttribute('name'));
    }

    public function testOrmBuilderProxiesJoinRawGroupAndHavingMethods(): void
    {
        $manager = $this->makeManager();
        OrmUser::setConnectionManager($manager);

        $query = OrmUser::query()
            ->select('orm_users.name')
            ->selectRaw('COUNT(posts.id) as post_count')
            ->leftJoin('posts', 'posts.user_id', '=', 'orm_users.id')
            ->whereRaw('posts.published = ?', [1])
            ->groupBy('orm_users.name')
            ->having('post_count', '>', 0);

        self::assertSame(
            'SELECT "orm_users"."name", COUNT(posts.id) as post_count FROM "orm_users" LEFT JOIN "posts" ON "posts"."user_id" = "orm_users"."id" WHERE (posts.published = ?) GROUP BY "orm_users"."name" HAVING ("post_count" > ?)',
            $query->getBaseBuilder()->toSql()
        );
        self::assertSame([1, 0], $query->getBaseBuilder()->getBindings());
    }

    public function testModelWhereSupportsTwoArgumentShorthand(): void
    {
        $manager = $this->makeManager();
        $pdo = $manager->getPdo();
        $pdo->exec('CREATE TABLE orm_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec("INSERT INTO orm_users (name) VALUES ('Alice')");

        OrmUser::setConnectionManager($manager);

        $result = OrmUser::where('name', 'Alice')->first();

        self::assertInstanceOf(OrmUser::class, $result);
        self::assertSame('Alice', $result?->getAttribute('name'));
    }

    public function testOrmBuilderProxiesDistinctWhereColumnAndRawGroupingMethods(): void
    {
        $manager = $this->makeManager();
        OrmUser::setConnectionManager($manager);

        $query = OrmUser::query()
            ->distinct()
            ->select('orm_users.name')
            ->selectRaw('COUNT(posts.id) as post_count')
            ->leftJoin('posts', 'posts.user_id', '=', 'orm_users.id')
            ->whereColumn('posts.user_id', 'orm_users.id')
            ->groupByRaw('orm_users.name')
            ->havingRaw('COUNT(posts.id) > ?', [0]);

        self::assertSame(
            'SELECT DISTINCT "orm_users"."name", COUNT(posts.id) as post_count FROM "orm_users" LEFT JOIN "posts" ON "posts"."user_id" = "orm_users"."id" WHERE ("posts"."user_id" = "orm_users"."id") GROUP BY orm_users.name HAVING (COUNT(posts.id) > ?)',
            $query->getBaseBuilder()->toSql()
        );
        self::assertSame([0], $query->getBaseBuilder()->getBindings());
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
}

final class OrmUser extends Model
{
    protected static ?string $table = 'orm_users';
}
