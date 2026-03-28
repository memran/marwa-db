<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\CLI;

use Marwa\DB\CLI\Commands\MakeSeederCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeSeederCommandTest extends TestCase
{
    public function testCommandCreatesSeederFileWithExpectedNamespace(): void
    {
        $dir = sys_get_temp_dir() . '/marwa-db-seeders-' . bin2hex(random_bytes(4));
        $command = new MakeSeederCommand($dir, 'Database\\Seeders');
        $tester = new CommandTester($command);

        try {
            $exitCode = $tester->execute([
                'name' => 'UsersTableSeeder',
            ]);

            $files = glob($dir . '/*.php') ?: [];

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertCount(1, $files);

            $contents = file_get_contents($files[0]);
            self::assertIsString($contents);
            self::assertStringContainsString('namespace Database\\Seeders;', $contents);
            self::assertStringContainsString('final class UsersTableSeeder implements Seeder', $contents);
        } finally {
            $files = glob($dir . '/*.php') ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    public function testCommandRejectsNonStudlyCaseSeederName(): void
    {
        $dir = sys_get_temp_dir() . '/marwa-db-seeders-' . bin2hex(random_bytes(4));
        $command = new MakeSeederCommand($dir, 'Database\\Seeders');
        $tester = new CommandTester($command);

        try {
            $exitCode = $tester->execute([
                'name' => 'usersTableSeeder',
            ]);

            self::assertSame(Command::FAILURE, $exitCode);
            self::assertStringContainsString('Class name must be StudlyCase', $tester->getDisplay());
        } finally {
            $files = glob($dir . '/*.php') ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
}
