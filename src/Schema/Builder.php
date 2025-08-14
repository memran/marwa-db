<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

use Marwa\DB\Connection\ConnectionManager;
use PDO;

final class Builder
{
    public function __construct(
        private ConnectionManager $cm,
        private string $connection = 'default'
    ) {}

    public static function useConnectionManager(ConnectionManager $cm): self
    {
        $newInstance = new self($cm); // Or new MyClass();
        return $newInstance;
    }
    /** Run a callback to define a new table. */
    public function create(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $sqls = $this->grammar()->compileCreate($blueprint);
        $this->run($sqls);
    }

    /** Alter an existing table (add columns/indexes). */
    public function table(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->setTableMode(Blueprint::MODE_ALTER);
        $callback($blueprint);
        $sqls = $this->grammar()->compileTable($blueprint);
        $this->run($sqls);
    }

    /** Drop table. */
    public function drop(string $table): void
    {
        $sqls = $this->grammar()->compileDrop($table);
        $this->run($sqls);
    }

    public function rename(string $from, string $to): void
    {
        $sqls = $this->grammar()->compileRename($from, $to);
        $this->run($sqls);
    }

    // ---- helpers

    private function grammar(): Grammar
    {
        $pdo = $this->pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        return match ($driver) {
            'sqlite' => new SQLiteGrammar(),
            default  => new MySqlGrammar(), // mysql/mariadb; extend as needed
        };
    }

    private function run(array $sqls): void
    {
        $pdo = $this->pdo();
        foreach ($sqls as $sql) {
            $pdo->exec($sql);
        }
    }

    private function pdo(): PDO
    {
        return $this->cm->getPdo($this->connection);
    }
}
