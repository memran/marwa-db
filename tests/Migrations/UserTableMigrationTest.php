<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Migrations;

use PHPUnit\Framework\TestCase;

final class UserTableMigrationTest extends TestCase
{
    public function testRollbackDropsUsersTable(): void
    {
        $path = dirname(__DIR__, 2) . '/database/migrations/2025_08_13_211954_user_table.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringContainsString("Schema::drop('users');", $contents);
    }
}
