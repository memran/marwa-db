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
        $dir = $this->tempDir('seeders');
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
            $this->removeTempDir($dir);
        }
    }

    public function testCommandRejectsNonStudlyCaseSeederName(): void
    {
        $dir = $this->tempDir('seeders-invalid');
        $command = new MakeSeederCommand($dir, 'Database\\Seeders');
        $tester = new CommandTester($command);

        try {
            $exitCode = $tester->execute([
                'name' => 'usersTableSeeder',
            ]);

            self::assertSame(Command::FAILURE, $exitCode);
            self::assertStringContainsString('Class name must be StudlyCase', $tester->getDisplay());
        } finally {
            $this->removeTempDir($dir);
        }
    }

    private function tempDir(string $prefix): string
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.test-tmp';
        $dir = $root . DIRECTORY_SEPARATOR . $prefix . '-' . bin2hex(random_bytes(4));

        if (!is_dir($root)) {
            mkdir($root, 0775, true);
        }

        mkdir($dir, 0775, true);

        return $dir;
    }

    private function removeTempDir(string $dir): void
    {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }
}
