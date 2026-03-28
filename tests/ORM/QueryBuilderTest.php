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
