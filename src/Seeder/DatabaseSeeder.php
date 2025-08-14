<?php

declare(strict_types=1);

namespace Marwa\DB\Seeder;

use ReflectionClass;

/**
 * Your Seeder interface must be:
 *
 *   namespace Database\Seeders;
 *   interface Seeder { public function run(): void; }
 *
 * This DatabaseSeeder will:
 *  - scan the seeders directory
 *  - require_once each PHP file
 *  - find classes in the given namespace that implement Seeder
 *  - exclude itself (and any other classes you pass via $exclude)
 *  - execute them in sorted order
 */
final class DatabaseSeeder implements Seeder
{
    /**
     * @param string      $seedPath      Absolute path to seeders directory (e.g. getcwd().'/database/seeders')
     * @param string      $seedNamespace Namespace prefix for seeders (default: 'Database\\Seeders')
     * @param string[]    $exclude       Class short-names to skip (default: ['DatabaseSeeder'])
     */
    public function __construct(
        private string $seedPath,
        private string $seedNamespace = '\\Marwa\\DB\\Seeder',
        private array $exclude = ['DatabaseSeeder'],
    ) {}

    public function run(): void
    {
        $seeders = $this->discoverSeeders($this->seedPath, $this->seedNamespace);

        // Exclude DatabaseSeeder itself and any custom exclusions
        $seeders = array_values(array_filter(
            $seeders,
            fn(string $cls) => !in_array($this->short($cls), $this->exclude, true)
        ));

        // Run deterministically (A..Z)
        sort($seeders, SORT_STRING);

        foreach ($seeders as $fqcn) {
            /** @var Seeder $instance */
            $instance = new $fqcn();
            $instance->run();
        }
    }

    /**
     * Find PHP classes under $path in the $namespace that implement Seeder.
     *
     * @return array<int, class-string<Seeder>>
     */
    private function discoverSeeders(string $path, string $namespace): array
    {
        if (!is_dir($path)) {
            return [];
        }

        // Require all PHP files in the directory
        $files = glob($path . DIRECTORY_SEPARATOR . '*.php') ?: [];
        foreach ($files as $file) {
            require_once $file;
        }

        $out = [];
        $nsPrefix = rtrim($namespace, '\\') . '\\';

        foreach (get_declared_classes() as $cls) {
            // Only consider classes in the target namespace
            if (!str_starts_with($cls, $nsPrefix)) {
                continue;
            }
            // Must be concrete & implement Seeder
            $rc = new ReflectionClass($cls);
            if ($rc->isAbstract()) {
                continue;
            }
            if (!$rc->implementsInterface(Seeder::class)) {
                continue;
            }
            /** @var class-string<Seeder> $cls */
            $out[] = $cls;
        }

        return $out;
    }

    private function short(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
