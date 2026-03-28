<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Connection;

use InvalidArgumentException;
use Marwa\DB\Connection\ConnectionFactory;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    public function testUnsupportedDriverThrowsClearException(): void
    {
        $factory = new ConnectionFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported driver: sqlite');

        $factory->makePdo([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }
}
