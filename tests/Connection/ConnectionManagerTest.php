<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Connection;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use PHPUnit\Framework\TestCase;

final class ConnectionManagerTest extends TestCase
{
    public function testTransactionUsesDefaultConnectionWhenNameIsOmitted(): void
    {
        $manager = new ConnectionManager(new Config([
            'default' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]));

        $pdo = $manager->getPdo();
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $result = $manager->transaction(static function (\PDO $connection): string {
            $connection->exec("INSERT INTO items (name) VALUES ('default')");

            return 'ok';
        }, null);

        self::assertSame('ok', $result);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn());
    }
}
