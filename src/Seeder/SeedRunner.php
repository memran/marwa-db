<?php

declare(strict_types=1);

namespace Marwa\DB\Seeder;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use Marwa\DB\Connection\ConnectionManager;
use Psr\Log\LoggerInterface;

final class SeedRunner
{
    public function __construct(
        private ConnectionManager $cm,
        private ?LoggerInterface $logger = null,
        private string $connection = 'default',
    ) {}

    public function run(Seeder|string $seeder): void
    {
        $faker = $this->faker();
        $instance = \is_string($seeder) ? new $seeder() : $seeder;

        if (!$instance instanceof Seeder) {
            throw new \InvalidArgumentException('Seeder must implement ' . Seeder::class);
        }

        $this->logger?->info('[seeder] starting ' . $instance::class);
        $this->cm->transaction(function () use ($instance, $faker) {
            $instance->run($faker);
        }, $this->connection);
        $this->logger?->info('[seeder] finished ' . $instance::class);
    }

    private function faker(): Generator
    {
        return FakerFactory::create(); // locale can be set if needed
    }
}
