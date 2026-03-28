<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Schema;

use Marwa\DB\Schema\Blueprint;
use Marwa\DB\Schema\MySqlGrammar;
use PHPUnit\Framework\TestCase;

final class MySqlGrammarTest extends TestCase
{
    public function testCompileCreateIncludesColumnsIndexesAndForeignKeys(): void
    {
        $blueprint = new Blueprint('posts');
        $blueprint->increments('id');
        $blueprint->string('title', 150);
        $blueprint->foreignId('user_id');
        $blueprint->unique('title');
        $blueprint->foreign('user_id', 'users', 'id', options: ['onDelete' => 'cascade']);

        $sql = (new MySqlGrammar())->compileCreate($blueprint)[0];

        self::assertStringContainsString('CREATE TABLE `posts`', $sql);
        self::assertStringContainsString('`id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY', $sql);
        self::assertStringContainsString('`title` VARCHAR(150) NOT NULL', $sql);
        self::assertStringContainsString('UNIQUE KEY `uniq_posts_title` (`title`)', $sql);
        self::assertStringContainsString('FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', $sql);
    }
}
