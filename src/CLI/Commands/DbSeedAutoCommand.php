<?php

declare(strict_types=1);

namespace Marwa\DB\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Marwa\DB\Seeder\SeedRunner;
use Marwa\DB\Seeder\Seeder;
use ReflectionClass;

final class DbSeedAutoCommand extends Command
{
    protected static $defaultName = 'db:seed:auto';

    public function __construct(
        private SeedRunner $runner,
        private string $seedPath = null,                     // e.g. project_root()/database/seeders
        private string $seedNamespace = 'Database\\Seeders',
        private array $exclude = ['DatabaseSeeder']           // optionally skip orchestrator
    ) {
        parent::__construct();
        $this->seedPath ??= getcwd() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders';
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Auto-scan the seeders directory and run all seeders')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only list seeders that would run')
            ->addOption('only', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Run only specific seeder classes (comma separated or repeated)')
            ->addOption('except', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Skip specific seeders (comma separated or repeated)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!is_dir($this->seedPath)) {
            $output->writeln('<comment>No seeders directory found:</comment> ' . $this->seedPath);
            return Command::SUCCESS;
        }

        $only   = $this->normalizeList($input->getOption('only'));
        $except = array_merge($this->exclude, $this->normalizeList($input->getOption('except')));
        $dry    = (bool)$input->getOption('dry-run');

        $seeders = $this->discoverSeeders($this->seedPath, $this->seedNamespace);

        if ($only) {
            $seeders = array_values(array_filter($seeders, fn($cls) => in_array($this->short($cls), $only, true)));
        }
        if ($except) {
            $seeders = array_values(array_filter($seeders, fn($cls) => !in_array($this->short($cls), $except, true)));
        }

        if (!$seeders) {
            $output->writeln('<comment>No seeders found to run.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Seeders detected:</info>');
        foreach ($seeders as $cls) {
            $output->writeln('  - ' . $cls);
        }

        if ($dry) {
            $output->writeln('<comment>Dry run complete. No seeders executed.</comment>');
            return Command::SUCCESS;
        }

        foreach ($seeders as $cls) {
            $output->writeln('<info>Running:</info> ' . $cls);
            $this->runner->run($cls);
        }

        $output->writeln('<info>All seeders executed.</info>');
        return Command::SUCCESS;
    }

    /**
     * Find PHP classes under $path that live in $namespace and implement Seeder.
     * It requires each file and inspects classes via reflection.
     *
     * @return array<int, class-string<Seeder>>
     */
    private function discoverSeeders(string $path, string $namespace): array
    {
        $files = glob($path . DIRECTORY_SEPARATOR . '*.php') ?: [];
        $classes = [];

        foreach ($files as $file) {
            require_once $file;

            $base = pathinfo($file, PATHINFO_FILENAME);
            $fqcn = rtrim($namespace, '\\') . '\\' . $base;

            if (!class_exists($fqcn)) {
                // Try to derive class by scanning declared classes in this file (fallback)
                foreach (get_declared_classes() as $cls) {
                    if (str_starts_with($cls, rtrim($namespace, '\\') . '\\') && str_ends_with($cls, '\\' . $base)) {
                        $fqcn = $cls;
                        break;
                    }
                }
            }

            if (!class_exists($fqcn)) {
                continue;
            }

            $rc = new ReflectionClass($fqcn);
            if ($rc->isAbstract()) continue;
            if (!$rc->implementsInterface(Seeder::class)) continue;

            /** @var class-string<Seeder> $fqcn */
            $classes[] = $fqcn;
        }

        // Sort by class name to get deterministic order
        sort($classes, SORT_STRING);
        return $classes;
    }

    /** Normalize InputOption VALUE_IS_ARRAY which may contain comma-separated elements */
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
