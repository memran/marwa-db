<?php

declare(strict_types=1);

namespace Marwa\DB\Seeder;

use Marwa\DB\Connection\ConnectionManager;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Marwa\DB\ORM\Model;

final class SeedRunner
{
    public function __construct(
        private ConnectionManager $cm,
        private ?LoggerInterface $logger = null,
        private string $connection = 'default',
        private string $seedPath = '',                     // e.g. getcwd().'/database/seeders'
        private string $seedNamespace = 'Marwa\\DB\\Seeder',
        private array $exclude = ['DatabaseSeeder'],        // class short-names to skip
    ) {}

    /** Run all discovered seeders in a single transaction (by default). */
    public function runAll(bool $wrapInTransaction = true, ?array $only = null, array $except = []): void
    {
        $classes = $this->discoverSeeders();
        Model::setConnectionManager($this->cm);

        // Filter by --only / --except + default exclusions
        $except = array_unique(array_merge($this->exclude, $except));
        if ($only) {
            $classes = array_values(array_filter(
                $classes,
                fn($cls) => in_array($this->short($cls), $only, true)
            ));
        }
        if ($except) {
            $classes = array_values(array_filter(
                $classes,
                fn($cls) => !in_array($this->short($cls), $except, true)
            ));
        }

        // Deterministic order
        sort($classes, SORT_STRING);

        $runner = function () use ($classes) {
            foreach ($classes as $fqcn) {
                $this->logger?->info('[seed] running ' . $fqcn);
                /** @var Seeder $instance */
                $instance = new $fqcn();
                $instance->run();
            }
        };

        if ($wrapInTransaction) {
            $this->cm->transaction(fn() => $runner(), $this->connection);
        } else {
            $runner();
        }
    }

    /** Run a single seeder class by FQCN (outside discovery). */
    public function runOne(string $fqcn, bool $wrapInTransaction = true): void
    {
        if (!class_exists($fqcn)) {
            throw new \InvalidArgumentException("Seeder class {$fqcn} not found.");
        }
        $exec = function () use ($fqcn) {
            /** @var Seeder $instance */
            $instance = new $fqcn();
            if (!$instance instanceof Seeder) {
                throw new \RuntimeException("{$fqcn} must implement " . Seeder::class);
            }
            $this->logger?->info('[seed] running ' . $fqcn);
            $instance->run();
        };

        $wrapInTransaction
            ? $this->cm->transaction(fn() => $exec(), $this->connection)
            : $exec();
    }

    /** @return array<int, class-string<Seeder>> */
    public function discoverSeeders(): array
    {

        $path = $this->seedPath ?: (getcwd() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders');

        if (!is_dir($path)) {
            $this->logger?->warning('[seed] seeders path not found: ' . $path);

            return [];
        }

        // Load files
        $files = glob($path . DIRECTORY_SEPARATOR . '*.php') ?: [];

        foreach ($files as $file) {
            require_once $file;
        }

        $nsPrefix = rtrim($this->seedNamespace, '\\') . '\\';
        $found = [];

        foreach (get_declared_classes() as $cls) {
            if (!str_starts_with($cls, $nsPrefix)) {
                continue;
            }
            $rc = new ReflectionClass($cls);
            if ($rc->isAbstract() || !$rc->implementsInterface(Seeder::class)) {
                continue;
            }
            /** @var class-string<Seeder> $cls */
            $found[] = $cls;
        }

        return $found;
    }

    private function short(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
