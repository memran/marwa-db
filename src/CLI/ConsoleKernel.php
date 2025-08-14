<?php

declare(strict_types=1);

namespace Marwa\DB\CLI;

use Symfony\Component\Console\Application;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\CLI\Commands\MigrateCommand;
use Marwa\DB\CLI\Commands\MigrateRollbackCommand;
use Marwa\DB\CLI\Commands\MigrateRefreshCommand;
use Marwa\DB\CLI\Commands\MakeMigrationCommand;
use Marwa\DB\CLI\Commands\MigrateStatusCommand;
use Marwa\DB\CLI\Commands\DbSeedCommand;

use Marwa\DB\Console\Commands\MakeSeederCommand;
use Marwa\DB\Console\Commands\DbSeedAutoCommand;
use Marwa\DB\Seeder\SeedRunner;

final class ConsoleKernel
{
    protected $logger;
    public function __construct(private ConnectionManager $manager, private string $migrationsPath) {}

    public function run(array $argv): int
    {

        $app = new Application('Marwa-DB CLI', '0.1.0');
        $app->add(new MigrateCommand($this->manager, $this->migrationsPath));
        $app->add(new MigrateRollbackCommand($this->manager, $this->migrationsPath));
        $app->add(new MigrateRefreshCommand($this->manager, $this->migrationsPath));
        $app->add(new MakeMigrationCommand($this->migrationsPath));
        $app->add(new MigrateStatusCommand($this->manager, $this->migrationsPath));
        // $app->add(new DbSeedCommand(
        //     new \Marwa\DB\Seeder\SeedRunner($this->manager, $logger)
        // ));

        // $app->add(new MakeSeederCommand(
        //     seedPath: getcwd() . '/database/seeders',
        //     seedNamespace: 'Database\\Seeders'
        // ));

        // $app->add(new DbSeedAutoCommand(
        //     runner: new SeedRunner($this->manager, $psrLogger),
        //     seedPath: getcwd() . '/database/seeders',
        //     seedNamespace: 'Database\\Seeders',
        //     exclude: ['DatabaseSeeder'] // can set [] to include it too
        // ));

        return $app->run();
    }

    protected function getLogger()
    {
        // Get the logger (manual logging)
        return $this->logger;
    }

    protected function enableErrorHandler() {}
}
