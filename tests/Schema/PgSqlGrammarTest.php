<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Schema;

use Marwa\DB\Schema\Blueprint;
use Marwa\DB\Schema\PgSqlGrammar;
use PHPUnit\Framework\TestCase;

final class PgSqlGrammarTest extends TestCase
{
    public function testCompileCreateIncludesPostgresSpecificTypesAndIndexes(): void
    {
        $blueprint = new Blueprint('posts');
        $blueprint->bigIncrements('id');
        $blueprint->string('title', 150);
        $blueprint->jsonb('meta')->nullable();
        $blueprint->foreignId('user_id');
        $blueprint->unique('title');
        $blueprint->foreign('user_id', 'users', 'id', options: ['onDelete' => 'cascade']);

        $sql = (new PgSqlGrammar())->compileCreate($blueprint);

        self::assertStringContainsString('CREATE TABLE "posts"', $sql[0]);
        self::assertStringContainsString('"id" BIGSERIAL NOT NULL PRIMARY KEY', $sql[0]);
        self::assertStringContainsString('"meta" JSONB NULL', $sql[0]);
        self::assertStringContainsString('FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE', $sql[0]);
        self::assertStringContainsString('CREATE UNIQUE INDEX "idx_posts_title" ON "posts" ("title")', $sql[1]);
    }
}
