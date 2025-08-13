<?php

namespace Marwa\DB\Migrations;

use Marwa\DB\Query\Builder as QueryBuilder;
use InvalidArgumentException;

class MigrationRepository
{
    /**
     * @var QueryBuilder
     */
    protected $connection;

    /**
     * @var string
     */
    protected $migrationsTable = 'migrations';

    /**
     * @var string
     */
    protected $migrationsPath;

    /**
     * Constructor
     *
     * @param QueryBuilder $connection
     * @param string $migrationsPath
     */
    public function __construct(QueryBuilder $connection, string $migrationsPath)
    {
        if (!is_dir($migrationsPath)) {
            throw new InvalidArgumentException("Invalid migrations path: {$migrationsPath}");
        }

        $this->connection = $connection;
        $this->migrationsPath = rtrim($migrationsPath, DIRECTORY_SEPARATOR);
    }

    /**
     * Get all ran migrations with batch and timestamp
     *
     * @return array
     */
    public function getRanWithDetails(): array
    {
        $rows = $this->connection->table($this->migrationsTable)->get();
        $details = [];

        foreach ($rows as $row) {
            $details[$row['migration']] = [
                'batch' => $row['batch'],
                'ran_at' => $row['ran_at'] ?? 'N/A'
            ];
        }

        return $details;
    }

    /**
     * Get all ran migration names
     *
     * @return array
     */
    public function getRan(): array
    {
        return $this->connection
            ->table($this->migrationsTable)
            ->pluck('migration')
            ->toArray();
    }

    /**
     * Get all migration file names
     *
     * @return array
     */
    public function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);

        return array_map(function ($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, $files);
    }

    /**
     * Log that a migration has run
     *
     * @param string $migration
     * @param int $batch
     * @return void
     */
    public function log(string $migration, int $batch): void
    {
        $this->connection->table($this->migrationsTable)->insert([
            'migration' => $migration,
            'batch' => $batch,
            'ran_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Remove a migration from the log (for rollback)
     *
     * @param string $migration
     * @return void
     */
    public function delete(string $migration): void
    {
        $this->connection
            ->table($this->migrationsTable)
            ->where('migration', '=', $migration)
            ->delete();
    }

    /**
     * Get the last batch number
     *
     * @return int
     */
    public function getLastBatchNumber(): int
    {
        return (int) $this->connection
            ->table($this->migrationsTable)
            ->max('batch');
    }

    /**
     * Get migrations from the last batch
     *
     * @return array
     */
    public function getLastBatch(): array
    {
        $batch = $this->getLastBatchNumber();

        return $this->connection
            ->table($this->migrationsTable)
            ->where('batch', '=', $batch)
            ->orderBy('id', 'desc')
            ->pluck('migration')
            ->toArray();
    }
}
