<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\CLI;

use Marwa\DB\CLI\Commands\MakeMigrationCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeMigrationCommandTest extends TestCase
{
    public function testCommandCreatesTimestampedMigrationFile(): void
    {
        $dir = sys_get_temp_dir() . '/marwa-db-tests-' . bin2hex(random_bytes(4));
        $command = new MakeMigrationCommand($dir);
        $tester = new CommandTester($command);

        try {
            $exitCode = $tester->execute([
                'name' => 'create-users table',
            ]);

            $files = glob($dir . '/*.php') ?: [];

            self::assertSame(0, $exitCode);
            self::assertCount(1, $files);
            self::assertMatchesRegularExpression('/\d{4}_\d{2}_\d{2}_\d{6}_create_users_table\.php$/', basename($files[0]));

            $contents = file_get_contents($files[0]);
            self::assertIsString($contents);
            self::assertStringContainsString('use Marwa\\DB\\Schema\\Schema;', $contents);
            self::assertStringContainsString('return new class extends AbstractMigration', $contents);
            self::assertStringContainsString("Schema::drop('example');", $contents);
        } finally {
            $files = glob($dir . '/*.php') ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
}
