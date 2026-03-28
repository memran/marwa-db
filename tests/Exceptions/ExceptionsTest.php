<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Exceptions;

use Marwa\DB\Exceptions\ConnectionException;
use Marwa\DB\Exceptions\ORMException;
use Marwa\DB\Exceptions\QueryException;
use Marwa\DB\Exceptions\SchemaException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionsTest extends TestCase
{
    public function testPackageExceptionsExtendRuntimeException(): void
    {
        self::assertInstanceOf(RuntimeException::class, new ConnectionException());
        self::assertInstanceOf(RuntimeException::class, new ORMException());
        self::assertInstanceOf(RuntimeException::class, new QueryException());
        self::assertInstanceOf(RuntimeException::class, new SchemaException());
    }
}
