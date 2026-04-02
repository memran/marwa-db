<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Connection;

use InvalidArgumentException;
use Marwa\DB\Connection\ConnectionFactory;
use PDO;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    public function testSqliteMemoryConnectionIsSupported(): void
    {
        $factory = new ConnectionFactory();

        $pdo = $factory->makePdo([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        self::assertInstanceOf(PDO::class, $pdo);
        self::assertSame('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testUnsupportedDriverThrowsClearException(): void
    {
        $factory = new ConnectionFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported driver: sqlsrv');

        $factory->makePdo([
            'driver' => 'sqlsrv',
        ]);
    }

    public function testMysqlConnectionsRequireDatabaseName(): void
    {
        $factory = new ConnectionFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MySQL connections require a database name.');

        $factory->makePdo([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => '',
        ]);
    }
}
