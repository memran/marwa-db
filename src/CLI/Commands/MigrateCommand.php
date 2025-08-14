<?php

declare(strict_types=1);

namespace Marwa\DB\CLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Schema\MigrationRepository;
use Marwa\DB\Schema\Builder as SchemaBuilder;

#[AsCommand(
    name: 'migrate',
    description: 'Run database migrations',
    help: 'This command performs a specific task. Use it carefully.'
)]
final class MigrateCommand extends Command
{

    public function __construct(private ConnectionManager $manager, private string $migrationsPath)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        SchemaBuilder::useConnectionManager($this->manager);

        $repo = new MigrationRepository($this->manager->getPdo(), $this->migrationsPath);
        $count = $repo->migrate();
        $output->writeln("<info>Migrations run: {$count}</info>");
        return Command::SUCCESS;
    }
}
