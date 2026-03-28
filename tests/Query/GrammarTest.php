<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Query;

use Marwa\DB\Query\Grammar;
use PHPUnit\Framework\TestCase;

final class GrammarTest extends TestCase
{
    public function testWrapEscapesBackticks(): void
    {
        $grammar = new Grammar();

        self::assertSame('`user``name`', $grammar->wrap('user`name'));
    }

    public function testParameterizeBuildsPlaceholderList(): void
    {
        $grammar = new Grammar();

        self::assertSame('?, ?, ?', $grammar->parameterize([1, 2, 3]));
    }
}
