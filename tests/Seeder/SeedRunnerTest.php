<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Seeder;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Facades\DB;
use Marwa\DB\Seeder\SeedRunner;
use PHPUnit\Framework\TestCase;

final class SeedRunnerTest extends TestCase
{
    public function testDiscoverSeedersFindsConcreteClassesInConfiguredNamespace(): void
    {
        $dir = $this->tempDir('seed-runner');

        $classPrefix = 'MarwaDBTests' . bin2hex(random_bytes(3));
        $namespace = $classPrefix . '\\Seeders';

        $alpha = $dir . '/AlphaSeeder.php';
        $beta = $dir . '/BetaSeeder.php';

        file_put_contents($alpha, $this->seederStub($namespace, 'AlphaSeeder'));
        file_put_contents($beta, $this->seederStub($namespace, 'BetaSeeder'));

        $runner = new SeedRunner(
            cm: new ConnectionManager(new Config(['default' => []])),
            seedPath: $dir,
            seedNamespace: $namespace
        );

        try {
            $seeders = $runner->discoverSeeders();

            sort($seeders);

            self::assertSame([
                $namespace . '\\AlphaSeeder',
                $namespace . '\\BetaSeeder',
            ], $seeders);
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testDefaultRunnerDiscoversSeedersRegardlessOfNamespace(): void
    {
        $dir = $this->tempDir('seed-runner-default');
        $file = $dir . '/UsersTableSeeder.php';

        file_put_contents($file, $this->seederStub('Database\\Seeders', 'UsersTableSeeder'));

        $runner = new SeedRunner(
            cm: new ConnectionManager(new Config(['default' => []])),
            seedPath: $dir
        );

        try {
            self::assertSame(['Database\\Seeders\\UsersTableSeeder'], $runner->discoverSeeders());
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testRunAllBootstrapsDbFacadeForFacadeBasedSeeders(): void
    {
        $dir = $this->tempDir('seed-runner-facade');
        $file = $dir . '/FacadeSeeder.php';
        $manager = new ConnectionManager(new Config([
            'default' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]));

        $manager->getPdo()->exec('CREATE TABLE seeded_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        file_put_contents($file, $this->facadeSeederStub('Database\\Seeders', 'FacadeSeeder'));

        $runner = new SeedRunner(
            cm: $manager,
            seedPath: $dir
        );

        try {
            $runner->runAll(false);

            self::assertSame(1, DB::table('seeded_users')->count());
        } finally {
            $this->removeTempDir($dir);
        }
    }

    private function seederStub(string $namespace, string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Marwa\DB\Seeder\Seeder;

final class {$class} implements Seeder
{
    public function run(): void
    {
    }
}
PHP;
    }

    private function facadeSeederStub(string $namespace, string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Marwa\DB\Facades\DB;
use Marwa\DB\Seeder\Seeder;

final class {$class} implements Seeder
{
    public function run(): void
    {
        DB::table('seeded_users')->insert([
            'name' => 'Seeder User',
        ]);
    }
}
PHP;
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
