<?php

declare(strict_types=1);

namespace Marwa\DB\CLI;

use Symfony\Component\Console\Application;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\CLI\Commands\MigrateCommand;
use Marwa\DB\CLI\Commands\MigrateRollbackCommand;
use Marwa\DB\CLI\Commands\MigrateRefreshCommand;
use Marwa\DB\CLI\Commands\MakeMigrationCommand;

final class ConsoleKernel
{
    public function __construct(private ConnectionManager $manager, private string $migrationsPath) {}

    public function run(array $argv): int
    {
        $app = new Application('Marwa-DB CLI', '0.1.0');
        $app->add(new MigrateCommand($this->manager, $this->migrationsPath));
        $app->add(new MigrateRollbackCommand($this->manager, $this->migrationsPath));
        $app->add(new MigrateRefreshCommand($this->manager, $this->migrationsPath));
        $app->add(new MakeMigrationCommand($this->migrationsPath));
        return $app->run();
    }
}
