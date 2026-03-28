<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Schema;

use Marwa\DB\Schema\MigrationRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigrationRepositoryTest extends TestCase
{
    public function testEnsureTableSupportsSqlite(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new MigrationRepository($pdo, __DIR__);

        $repo->ensureTable();

        $table = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'migrations'")->fetchColumn();

        self::assertSame('migrations', $table);
    }

    public function testGetRanWithDetailsReturnsEmptyArrayWhenTableIsFresh(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new MigrationRepository($pdo, __DIR__);

        self::assertSame([], $repo->getRanWithDetails());
    }
}
