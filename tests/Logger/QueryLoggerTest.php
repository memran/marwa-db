<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Logger;

use Marwa\DB\Logger\QueryLogger;
use PHPUnit\Framework\TestCase;

final class QueryLoggerTest extends TestCase
{
    public function testLogStoresEntriesAndClearResetsThem(): void
    {
        $logger = new QueryLogger();
        $logger->log('SELECT * FROM users', ['active' => 1], 1.25, 'default');

        self::assertCount(1, $logger->all());

        $logger->clear();

        self::assertSame([], $logger->all());
    }
}
