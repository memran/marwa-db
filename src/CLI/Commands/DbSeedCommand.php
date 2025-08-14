<?php

declare(strict_types=1);

namespace Marwa\DB\CLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Marwa\DB\Seeder\SeedRunner;
use Marwa\DB\Seeder\DatabaseSeeder;

#[AsCommand(
    name: 'db:seed',
    description: 'Run database seeders',
    help: 'This command performs a specific task. Use it carefully.'
)]
final class DbSeedCommand extends Command
{

    public function __construct(
        private SeedRunner $runner,
        private string $defaultSeeder = DatabaseSeeder::class,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run database seeders')
            ->addOption('class', null, InputOption::VALUE_REQUIRED, 'Seeder class to run', $this->defaultSeeder);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = (string)$input->getOption('class');
        $output->writeln("<info>Seeding:</info> {$class}");
        $this->runner->run($class);
        $output->writeln('<info>Done.</info>');
        return Command::SUCCESS;
    }
}
