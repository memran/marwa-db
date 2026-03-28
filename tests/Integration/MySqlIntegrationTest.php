<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Integration;

use Marwa\DB\Bootstrap;
use Marwa\DB\Facades\DB;
use Marwa\DB\ORM\Model;
use Marwa\DB\Schema\MigrationRepository;
use Marwa\DB\Schema\Schema;
use Marwa\DB\Seeder\SeedRunner;
use PHPUnit\Framework\TestCase;

final class MySqlIntegrationTest extends TestCase
{
    private const TABLE = 'integration_users';

    protected function setUp(): void
    {
        if (getenv('MARWA_DB_INTEGRATION') !== '1') {
            self::markTestSkipped('Set MARWA_DB_INTEGRATION=1 to run MySQL integration tests.');
        }

        $manager = $this->makeManager();
        DB::setManager($manager);
        Model::setConnectionManager($manager);
        Schema::init($manager);

        $manager->getPdo()->exec('DROP TABLE IF EXISTS `' . self::TABLE . '`');
    }

    public function testQueryBuilderCrudAgainstRealMysql(): void
    {
        Schema::create(self::TABLE, function ($table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('email', 190)->unique();
            $table->timestamps();
        });

        DB::table(self::TABLE)->insert([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $row = DB::table(self::TABLE)
            ->where('email', '=', 'alice@example.com')
            ->first();

        self::assertIsArray($row);
        self::assertSame('Alice', $row['name']);

        $updated = DB::table(self::TABLE)
            ->where('email', '=', 'alice@example.com')
            ->update(['name' => 'Alice Updated']);

        self::assertSame(1, $updated);
        self::assertSame(
            'Alice Updated',
            DB::table(self::TABLE)->where('email', '=', 'alice@example.com')->value('name')
        );
    }

    public function testMigrationRepositoryAndSeederRunnerAgainstRealMysql(): void
    {
        $manager = $this->makeManager();
        Schema::init($manager);

        $migrationDir = sys_get_temp_dir() . '/marwa-db-migrations-' . bin2hex(random_bytes(4));
        $seederDir = sys_get_temp_dir() . '/marwa-db-seeders-' . bin2hex(random_bytes(4));
        mkdir($migrationDir, 0775, true);
        mkdir($seederDir, 0775, true);

        $migrationFile = $migrationDir . '/2026_01_01_000000_create_integration_users_table.php';
        $seederFile = $seederDir . '/IntegrationUsersSeeder.php';

        file_put_contents($migrationFile, <<<PHP
<?php

use Marwa\DB\CLI\AbstractMigration;
use Marwa\DB\Schema\Schema;

return new class extends AbstractMigration {
    public function up(): void
    {
        Schema::create('integration_users', function (\$table) {
            \$table->bigIncrements('id');
            \$table->string('name', 100);
        });
    }

    public function down(): void
    {
        Schema::drop('integration_users');
    }
};
PHP);

        file_put_contents($seederFile, <<<PHP
<?php

namespace Database\\Seeders;

use Marwa\DB\Seeder\Seeder;
use Marwa\DB\Facades\DB;

final class IntegrationUsersSeeder implements Seeder
{
    public function run(): void
    {
        DB::table('integration_users')->insert([
            'name' => 'Seeded User',
        ]);
    }
}
PHP);

        try {
            $repo = new MigrationRepository($manager->getPdo(), $migrationDir);
            $ran = $repo->migrate();

            $runner = new SeedRunner(
                cm: $manager,
                seedPath: $seederDir
            );
            $runner->runAll(false);

            self::assertSame(1, $ran);
            self::assertSame(1, DB::table('integration_users')->count());

            $rolledBack = $repo->rollbackLastBatch();
            self::assertSame(1, $rolledBack);
        } finally {
            @unlink($migrationFile);
            @unlink($seederFile);
            @rmdir($migrationDir);
            @rmdir($seederDir);
            $manager->getPdo()->exec('DROP TABLE IF EXISTS `integration_users`');
            $manager->getPdo()->exec('DROP TABLE IF EXISTS `migrations`');
        }
    }

    private function makeManager(): \Marwa\DB\Connection\ConnectionManager
    {
        return Bootstrap::init([
            'default' => [
                'driver' => 'mysql',
                'host' => getenv('MARWA_DB_HOST') ?: '127.0.0.1',
                'port' => (int)(getenv('MARWA_DB_PORT') ?: 3306),
                'database' => getenv('MARWA_DB_DATABASE') ?: 'marwa_test',
                'username' => getenv('MARWA_DB_USERNAME') ?: 'root',
                'password' => getenv('MARWA_DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4',
                'debug' => false,
            ],
        ]);
    }
}
