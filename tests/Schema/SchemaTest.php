<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Schema;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        $factory = \Closure::bind(static function (): void {
            Schema::$factory = null;
        }, null, Schema::class);

        $factory();
    }

    public function testInitUsesRequestedConnectionName(): void
    {
        $tempDir = $this->tempDir('schema');
        $defaultPath = tempnam($tempDir, 'marwa-default-');
        $secondaryPath = tempnam($tempDir, 'marwa-secondary-');

        if ($defaultPath === false || $secondaryPath === false) {
            self::fail('Could not create temporary SQLite files.');
        }

        $manager = new ConnectionManager(new Config([
            'default' => [
                'driver' => 'sqlite',
                'database' => $defaultPath,
            ],
            'reporting' => [
                'driver' => 'sqlite',
                'database' => $secondaryPath,
            ],
        ]));

        try {
            Schema::init($manager, 'reporting');
            Schema::create('schema_switch_test', static function ($table): void {
                $table->increments('id');
            });

            $defaultTables = $manager->getPdo('default')
                ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'schema_switch_test'")
                ->fetchAll();
            $reportingTables = $manager->getPdo('reporting')
                ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'schema_switch_test'")
                ->fetchAll();

            self::assertCount(0, $defaultTables);
            self::assertCount(1, $reportingTables);
        } finally {
            @unlink($defaultPath);
            @unlink($secondaryPath);
            @rmdir($tempDir);
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
}
