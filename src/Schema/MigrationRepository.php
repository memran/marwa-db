<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

final class MigrationRepository
{
    public function __construct(private \PDO $pdo, private string $path) {}

    public function ensureTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function migrate(): int
    {
        $this->ensureTable();
        $files = glob(rtrim($this->path, '/') . '/*.php') ?: [];
        $done = $this->migratedNames();
        $batch = $this->nextBatch();

        $ran = 0;
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $done, true)) continue;

            $migration = require $file;
            $migration->up();
            $stmt = $this->pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
            $stmt->execute([$name, $batch]);
            $ran++;
        }
        return $ran;
    }

    public function rollbackLastBatch(): int
    {
        $this->ensureTable();
        $batch = $this->lastBatch();
        if ($batch === 0) return 0;

        $names = $this->namesByBatch($batch);
        $rolled = 0;

        foreach (array_reverse($names) as $name) {
            $file = rtrim($this->path, '/') . "/{$name}.php";
            if (!is_file($file)) continue;
            $migration = require $file;
            $migration->down();
            $stmt = $this->pdo->prepare('DELETE FROM migrations WHERE migration = ?');
            $stmt->execute([$name]);
            $rolled++;
        }
        return $rolled;
    }

    public function rollbackAll(): int
    {
        $total = 0;
        while (($c = $this->rollbackLastBatch()) > 0) {
            $total += $c;
        }
        return $total;
    }

    /** @return array<int,string> */
    private function migratedNames(): array
    {
        $rows = $this->pdo->query('SELECT migration FROM migrations ORDER BY id ASC')->fetchAll();
        return array_map(fn($r) => $r['migration'], $rows ?: []);
    }

    private function nextBatch(): int
    {
        $row = $this->pdo->query('SELECT MAX(batch) as b FROM migrations')->fetch();
        return (int)($row['b'] ?? 0) + 1;
    }

    private function lastBatch(): int
    {
        $row = $this->pdo->query('SELECT MAX(batch) as b FROM migrations')->fetch();
        return (int)($row['b'] ?? 0);
    }

    /** @return array<int,string> */
    private function namesByBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare('SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC');
        $stmt->execute([$batch]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => $r['migration'], $rows ?: []);
    }
}
