<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Query;

use Marwa\DB\Query\Pagination;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function testMakeBuildsExpectedPaginationPayload(): void
    {
        $pagination = new Pagination();

        $result = $pagination->make(['a', 'b'], 12, 5, 2);

        self::assertSame(
            [
                'data' => ['a', 'b'],
                'total' => 12,
                'per_page' => 5,
                'current_page' => 2,
                'last_page' => 3,
            ],
            $result
        );
    }
}
