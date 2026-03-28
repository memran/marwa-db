<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Query;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Query\Builder;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class BuilderTest extends TestCase
{
    public function testToSqlAndBindingsPreserveWhereOrder(): void
    {
        $builder = $this->makeBuilder();

        $builder
            ->table('users')
            ->select('id', 'email')
            ->where('status', '=', 'active')
            ->where('age', '>=', 18)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->offset(5);

        self::assertSame(
            'SELECT `id`, `email` FROM `users` WHERE (`status` = ?) AND (`age` >= ?) ORDER BY `id` DESC LIMIT 10 OFFSET 5',
            $builder->toSql()
        );
        self::assertSame(['active', 18], $builder->getBindings());
    }

    public function testWhereInWithEmptyArrayCompilesToAlwaysFalseClause(): void
    {
        $builder = $this->makeBuilder();

        $builder->table('users')->whereIn('id', []);

        self::assertSame(
            'SELECT * FROM `users` WHERE (1 = 0)',
            $builder->toSql()
        );
        self::assertSame([], $builder->getBindings());
    }

    public function testInsertUpdateAndDeleteExecuteAgainstInjectedPdo(): void
    {
        $builder = $this->makeBuilderWithSqlitePool();
        $pdo = $this->extractPdo($builder);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, status TEXT)');

        $builder->table('users')->insert([
            'email' => 'first@example.com',
            'status' => 'pending',
        ]);

        $builder->clear();
        $updated = $builder
            ->table('users')
            ->where('email', '=', 'first@example.com')
            ->update(['status' => 'active']);

        $builder->clear();
        $deleted = $builder
            ->table('users')
            ->where('status', '=', 'active')
            ->delete();

        $remaining = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        self::assertSame(1, $updated);
        self::assertSame(1, $deleted);
        self::assertSame(0, $remaining);
    }

    public function testInvalidOperatorIsRejected(): void
    {
        $builder = $this->makeBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator');

        $builder->table('users')->where('email', 'union select', 'x');
    }

    private function makeBuilder(): Builder
    {
        $cm = new ConnectionManager(new Config([
            'default' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'database' => 'test',
                'username' => 'root',
                'password' => '',
            ],
        ]));

        return new Builder($cm);
    }

    private function makeBuilderWithSqlitePool(): Builder
    {
        $cm = new ConnectionManager(new Config([
            'default' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'database' => 'test',
                'username' => 'root',
                'password' => '',
            ],
        ]));

        $setPool = \Closure::bind(static function (ConnectionManager $manager, array $pool): void {
            $manager->pool = $pool;
        }, null, ConnectionManager::class);

        $setPool($cm, [
            'default' => new PDO('sqlite::memory:'),
        ]);

        return new Builder($cm);
    }

    private function extractPdo(Builder $builder): PDO
    {
        $getConnectionManager = \Closure::bind(static function (Builder $queryBuilder): ConnectionManager {
            return $queryBuilder->cm;
        }, null, Builder::class);

        $cm = $getConnectionManager($builder);

        return $cm->getPdo();
    }
}
