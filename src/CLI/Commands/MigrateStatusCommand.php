<?php

declare(strict_types=1);

namespace Marwa\DB\CLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Query\Builder as QueryBuilder;
use Marwa\DB\Migrations\MigrationRepository;

#[AsCommand(
    name: 'migrate:status',
    description: 'Migration status list',
    help: 'This command performs a specific task. Use it carefully.'
)]
final class MigrateStatusCommand extends Command
{

    public function __construct(
        private ConnectionManager $manager,
        private string $migrationsPath
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Display all migrations with status/batch/timestamp')
            ->addOption('only-pending', null, InputOption::VALUE_NONE, 'Show only pending migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $onlyPending = (bool)$input->getOption('only-pending');

        // Build a query builder using the existing ConnectionManager
        $qb = new QueryBuilder($this->manager, 'default');

        // Create repository (expects QueryBuilder)
        $repo = new MigrationRepository($qb, $this->migrationsPath);

        $ran = $repo->getRanWithDetails();     // ['name' => ['batch'=>..,'ran_at'=>..], ...]
        $all = $repo->getMigrationFiles();     // ['2025_08_01_000000_create_users_table', ...]

        if (!$all) {
            $output->writeln('<comment>No migrations found.</comment>');
            return Command::SUCCESS;
        }

        $rows = [];
        $pending = 0;

        foreach ($all as $name) {
            $isRan = isset($ran[$name]);
            if ($onlyPending && $isRan) continue;

            if ($isRan) {
                $rows[] = [$name, '<info>Yes</info>', $ran[$name]['batch'], $ran[$name]['ran_at']];
            } else {
                $rows[] = [$name, '<error>No</error>', '-', '-'];
                $pending++;
            }
        }

        if (!$rows) {
            $output->writeln('<comment>No matching migrations found.</comment>');
            return Command::SUCCESS;
        }

        (new Table($output))
            ->setHeaders(['Migration', 'Ran?', 'Batch', 'Ran At'])
            ->setRows($rows)
            ->render();

        if (!$onlyPending) {
            $output->writeln("\n<info>Total Pending Migrations:</info> {$pending}");
        }

        return Command::SUCCESS;
    }
}
