<?php

declare(strict_types=1);

namespace Marwa\DB\CLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Marwa\DB\Seeder\SeedRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'db:seed',
    description: 'Auto-discover seeders and run them (deterministic order)',
    help: 'This command performs a specific task. Use it carefully.'
)]
final class DbSeedAutoCommand extends Command
{

    public function __construct(private SeedRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List seeders without executing')
            ->addOption('only', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Run only specific seeders by short class name', [])
            ->addOption('except', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Skip specific seeders by short class name', [])
            ->addOption('no-transaction', null, InputOption::VALUE_NONE, 'Do not wrap seeding in a transaction');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $only        = $this->normalizeList($input->getOption('only'));
        $except      = $this->normalizeList($input->getOption('except'));
        $dry         = (bool)$input->getOption('dry-run');
        $transaction = !$input->getOption('no-transaction');

        $classes = $this->runner->discoverSeeders();

        // Apply filters
        if ($only) {
            $classes = array_values(array_filter($classes, fn($cls) => in_array($this->short($cls), $only, true)));
        }
        if ($except) {
            $classes = array_values(array_filter($classes, fn($cls) => !in_array($this->short($cls), $except, true)));
        }

        sort($classes, SORT_STRING);

        if (!$classes) {
            $output->writeln('<comment>No seeders found.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Seeders detected:</info>');
        foreach ($classes as $c) {
            $output->writeln('  - ' . $c);
        }

        if ($dry) {
            $output->writeln('<comment>Dry run. Nothing executed.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Running seeders...</info>');
        $this->runner->runAll($transaction, $only ?: null, $except);
        $output->writeln('<info>Done.</info>');

        return Command::SUCCESS;
    }

    /** Normalize VALUE_IS_ARRAY options that might contain comma-separated lists */
    private function normalizeList(array $in): array
    {
        $out = [];
        foreach ($in as $item) {
            foreach (array_map('trim', explode(',', (string)$item)) as $v) {
                if ($v !== '') $out[] = $v;
            }
        }
        return array_values(array_unique($out));
    }

    private function short(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
