<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Schema;

use Marwa\DB\Schema\Blueprint;
use Marwa\DB\Schema\SQLiteGrammar;
use PHPUnit\Framework\TestCase;

final class SQLiteGrammarTest extends TestCase
{
    public function testCompileCreateSplitsIndexesIntoSeparateStatements(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->increments('id');
        $blueprint->string('email', 190)->unique();
        $blueprint->timestamps();

        $sql = (new SQLiteGrammar())->compileCreate($blueprint);

        self::assertCount(2, $sql);
        self::assertStringContainsString('CREATE TABLE "users"', $sql[0]);
        self::assertStringContainsString('"created_at" TEXT', $sql[0]);
        self::assertStringContainsString('CREATE UNIQUE INDEX "idx_users_email" ON "users" ("email")', $sql[1]);
    }
}
