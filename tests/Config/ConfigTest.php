<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Config;

use Marwa\DB\Config\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testGetSupportsNamedConnectionsArray(): void
    {
        $config = new Config([
            'default' => [
                'driver' => 'mysql',
                'database' => 'app',
            ],
        ]);

        self::assertSame('mysql', $config->get()['driver']);
    }

    public function testGetSupportsLegacyDefaultStringPlusConnectionsShape(): void
    {
        $config = new Config([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
        ]);

        self::assertSame('sqlite', $config->get()['driver']);
        self::assertSame(':memory:', $config->get('sqlite')['database']);
    }
}
