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

    public function testWhereSupportsTwoArgumentShorthand(): void
    {
        $builder = $this->makeBuilder();

        $builder->table('users')->where('email', 'jane@example.com');

        self::assertSame(
            'SELECT * FROM `users` WHERE (`email` = ?)',
            $builder->toSql()
        );
        self::assertSame(['jane@example.com'], $builder->getBindings());
    }

    public function testDistinctNestedWhereAndWhereColumnCompileExpectedSql(): void
    {
        $builder = $this->makeBuilder();

        $builder
            ->table('users')
            ->distinct()
            ->select('email')
            ->where(function (Builder $query): void {
                $query->where('status', 'active')->orWhere('status', 'pending');
            })
            ->whereColumn('users.account_id', 'accounts.id');

        self::assertSame(
            'SELECT DISTINCT `email` FROM `users` WHERE ((`status` = ?) OR (`status` = ?)) AND (`users`.`account_id` = `accounts`.`id`)',
            $builder->toSql()
        );
        self::assertSame(['active', 'pending'], $builder->getBindings());
    }

    public function testJoinGroupByHavingAndBindingsCompileInExecutionOrder(): void
    {
        $builder = $this->makeBuilder();

        $builder
            ->table('users as u')
            ->select('u.status')
            ->selectRaw('COUNT(p.id) as post_count')
            ->leftJoin('posts as p', 'p.user_id', '=', 'u.id')
            ->whereRaw('DATE(u.created_at) = ?', ['2026-05-19'])
            ->groupBy('u.status')
            ->having('post_count', '>', 1)
            ->orderBy('u.status');

        self::assertSame(
            'SELECT `u`.`status`, COUNT(p.id) as post_count FROM `users` AS `u` LEFT JOIN `posts` AS `p` ON `p`.`user_id` = `u`.`id` WHERE (DATE(u.created_at) = ?) GROUP BY `u`.`status` HAVING (`post_count` > ?) ORDER BY `u`.`status` ASC',
            $builder->toSql()
        );
        self::assertSame(['2026-05-19', 1], $builder->getBindings());
    }

    public function testGroupByRawAndHavingRawCompileExpectedSql(): void
    {
        $builder = $this->makeBuilder();

        $builder
            ->table('orders')
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as total_orders')
            ->groupByRaw('DATE(created_at)')
            ->havingRaw('COUNT(*) > ?', [5]);

        self::assertSame(
            'SELECT DATE(created_at) as day, COUNT(*) as total_orders FROM `orders` GROUP BY DATE(created_at) HAVING (COUNT(*) > ?)',
            $builder->toSql()
        );
        self::assertSame([5], $builder->getBindings());
    }

    public function testWhereBetweenAndWhereNotBetweenCompileExpectedSql(): void
    {
        $builder = $this->makeBuilder();

        $builder
            ->table('users')
            ->whereBetween('created_at', ['2026-05-01', '2026-05-31'])
            ->whereNotBetween('id', [10, 20]);

        self::assertSame(
            'SELECT * FROM `users` WHERE (`created_at` BETWEEN ? AND ?) AND (`id` NOT BETWEEN ? AND ?)',
            $builder->toSql()
        );
        self::assertSame(['2026-05-01', '2026-05-31', 10, 20], $builder->getBindings());
    }

    public function testJoinGroupByAndHavingExecuteAgainstSqlite(): void
    {
        $builder = $this->makeBuilderWithSqlitePool();
        $pdo = $this->extractPdo($builder);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, published INTEGER)');
        $pdo->exec("INSERT INTO users (name, status) VALUES ('Alice', 'active')");
        $pdo->exec("INSERT INTO users (name, status) VALUES ('Bob', 'inactive')");
        $pdo->exec('INSERT INTO posts (user_id, published) VALUES (1, 1)');
        $pdo->exec('INSERT INTO posts (user_id, published) VALUES (1, 1)');
        $pdo->exec('INSERT INTO posts (user_id, published) VALUES (2, 1)');

        $rows = $builder
            ->table('users')
            ->select('users.status')
            ->selectRaw('COUNT(posts.id) as post_count')
            ->join('posts', 'posts.user_id', '=', 'users.id')
            ->whereBetween('posts.published', [1, 1])
            ->groupBy('users.status')
            ->having('users.status', '=', 'active')
            ->get();

        self::assertCount(1, $rows);
        self::assertSame('active', $rows[0]['status']);
        self::assertSame(2, (int) $rows[0]['post_count']);
    }

    public function testWhereBetweenRequiresExactlyTwoValues(): void
    {
        $builder = $this->makeBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('whereBetween expects exactly two values.');

        $builder->table('users')->whereBetween('created_at', ['2026-05-01']);
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

    public function testInsertGetIdAndAtomicCountersExecuteAgainstInjectedPdo(): void
    {
        $builder = $this->makeBuilderWithSqlitePool();
        $pdo = $this->extractPdo($builder);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, logins INTEGER DEFAULT 0)');

        $id = $builder->table('users')->insertGetId([
            'email' => 'counter@example.com',
            'logins' => 1,
        ]);

        $builder->clear();
        $builder->table('users')->where('id', (int) $id)->increment('logins', 2);

        $builder->clear();
        $builder->table('users')->where('id', (int) $id)->decrement('logins');

        $logins = (int) $pdo->query('SELECT logins FROM users WHERE id = 1')->fetchColumn();

        self::assertSame('1', (string) $id);
        self::assertSame(2, $logins);
    }

    public function testChunkProcessesRowsInBatchesWithoutMutatingBuilder(): void
    {
        $builder = $this->makeBuilderWithSqlitePool();
        $pdo = $this->extractPdo($builder);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)');
        $pdo->exec("INSERT INTO users (email) VALUES ('a@example.com')");
        $pdo->exec("INSERT INTO users (email) VALUES ('b@example.com')");
        $pdo->exec("INSERT INTO users (email) VALUES ('c@example.com')");

        $query = $builder->table('users')->orderBy('id', 'asc');
        $seen = [];
        $offsets = [];

        $query->chunk(2, function (int $offset, array $rows) use (&$seen, &$offsets): void {
            $offsets[] = $offset;
            foreach ($rows as $row) {
                $seen[] = $row['email'];
            }
        });

        self::assertSame([0, 2], $offsets);
        self::assertSame(['a@example.com', 'b@example.com', 'c@example.com'], $seen);
        self::assertSame('SELECT * FROM `users` ORDER BY `id` ASC', $query->toSql());
    }

    public function testChunkByIdProcessesRowsInIdOrderWithoutMutatingBuilder(): void
    {
        $builder = $this->makeBuilderWithSqlitePool();
        $pdo = $this->extractPdo($builder);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)');
        $pdo->exec("INSERT INTO users (email) VALUES ('a@example.com')");
        $pdo->exec("INSERT INTO users (email) VALUES ('b@example.com')");
        $pdo->exec("INSERT INTO users (email) VALUES ('c@example.com')");

        $query = $builder->table('users');
        $seen = [];
        $ids = [];

        $query->chunkById(2, function (int|string $lastId, array $rows) use (&$seen, &$ids): void {
            $ids[] = (int) $lastId;
            foreach ($rows as $row) {
                $seen[] = $row['email'];
            }
        });

        self::assertSame([2, 3], $ids);
        self::assertSame(['a@example.com', 'b@example.com', 'c@example.com'], $seen);
        self::assertSame('SELECT * FROM `users`', $query->toSql());
    }

    public function testWhereExistsAndWhereNotExistsCompileAndExecuteExpectedSql(): void
    {
        $builder = $this->makeBuilderWithSqlitePool();
        $pdo = $this->extractPdo($builder);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)');
        $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, published INTEGER)');
        $pdo->exec("INSERT INTO users (email) VALUES ('a@example.com')");
        $pdo->exec("INSERT INTO users (email) VALUES ('b@example.com')");
        $pdo->exec('INSERT INTO posts (user_id, published) VALUES (1, 1)');

        $sqlBuilder = $this->makeBuilder();
        $sqlBuilder
            ->table('users')
            ->whereExists(function (Builder $query): void {
                $query
                    ->table('posts')
                    ->selectRaw('1')
                    ->whereRaw('posts.user_id = users.id')
                    ->where('published', 1);
            })
            ->whereNotExists(function (Builder $query): void {
                $query
                    ->table('posts')
                    ->selectRaw('1')
                    ->whereRaw('posts.user_id = users.id')
                    ->where('published', 0);
            });

        self::assertSame(
            'SELECT * FROM `users` WHERE (EXISTS (SELECT 1 FROM `posts` WHERE (posts.user_id = users.id) AND (`published` = ?))) AND (NOT EXISTS (SELECT 1 FROM `posts` WHERE (posts.user_id = users.id) AND (`published` = ?)))',
            $sqlBuilder->toSql()
        );
        self::assertSame([1, 0], $sqlBuilder->getBindings());

        $rows = $builder
            ->table('users')
            ->whereExists(function (Builder $query): void {
                $query
                    ->table('posts')
                    ->selectRaw('1')
                    ->whereRaw('posts.user_id = users.id')
                    ->where('published', 1);
            })
            ->get();

        self::assertCount(1, $rows);
        self::assertSame('a@example.com', $rows[0]['email']);
    }

    public function testInvalidOperatorIsRejected(): void
    {
        $builder = $this->makeBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator');

        $builder->table('users')->where('email', 'union select', 'x');
    }

    public function testPaginateReturnsExpectedPayload(): void
    {
        $builder = $this->makeBuilderWithSqlitePool();
        $pdo = $this->extractPdo($builder);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)');
        $pdo->exec("INSERT INTO users (email) VALUES ('a@example.com')");
        $pdo->exec("INSERT INTO users (email) VALUES ('b@example.com')");
        $pdo->exec("INSERT INTO users (email) VALUES ('c@example.com')");

        $payload = $builder
            ->table('users')
            ->orderBy('id', 'asc')
            ->paginate(2, 2);

        self::assertSame(3, $payload['total']);
        self::assertSame(2, $payload['per_page']);
        self::assertSame(2, $payload['current_page']);
        self::assertSame(2, $payload['last_page']);
        self::assertCount(1, $payload['data']);
        self::assertSame('c@example.com', $payload['data'][0]['email']);
    }

    public function testPostgresDriverUsesDoubleQuotedIdentifiers(): void
    {
        $builder = $this->makePostgresBuilder();

        $builder
            ->table('users')
            ->select('id', 'email')
            ->where('email', '=', 'jane@example.com')
            ->orderBy('id', 'desc');

        self::assertSame(
            'SELECT "id", "email" FROM "users" WHERE ("email" = ?) ORDER BY "id" DESC',
            $builder->toSql()
        );
    }

    public function testAggregateAndProjectionHelpersDoNotMutateBuilderState(): void
    {
        $builder = $this->makeBuilderWithSqlitePool();
        $pdo = $this->extractPdo($builder);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, status TEXT)');
        $pdo->exec("INSERT INTO users (email, status) VALUES ('first@example.com', 'active')");
        $pdo->exec("INSERT INTO users (email, status) VALUES ('second@example.com', 'inactive')");

        $query = $builder
            ->table('users')
            ->select('id', 'email')
            ->where('status', '=', 'active')
            ->orderBy('id', 'desc');

        $sqlBefore = $query->toSql();
        $bindingsBefore = $query->getBindings();

        self::assertSame(1, $query->count());
        self::assertSame('first@example.com', $query->value('email'));
        self::assertSame(['first@example.com'], $query->pluck('email')->toArray());
        self::assertSame($sqlBefore, $query->toSql());
        self::assertSame($bindingsBefore, $query->getBindings());
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

    private function makePostgresBuilder(): Builder
    {
        $cm = new ConnectionManager(new Config([
            'default' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'test',
                'username' => 'postgres',
                'password' => '',
            ],
        ]));

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
