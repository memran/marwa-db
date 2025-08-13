<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

final class Blueprint
{
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];

    public function __construct(private string $table) {}

    public function increments(string $name): self
    {
        $this->columns[] = "`{$name}` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $col = new ColumnDefinition("`{$name}` VARCHAR({$length})");
        $this->columns[] = $col;
        return $col;
    }

    public function integer(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`{$name}` INT");
        $this->columns[] = $col;
        return $col;
    }

    public function boolean(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`{$name}` TINYINT(1)");
        $this->columns[] = $col;
        return $col;
    }

    public function timestamps(): self
    {
        $this->columns[] = "`created_at` TIMESTAMP NULL DEFAULT NULL";
        $this->columns[] = "`updated_at` TIMESTAMP NULL DEFAULT NULL";
        return $this;
    }

    public function primary(string|array $cols): self
    {
        $cols = (array)$cols;
        $this->indexes[] = 'PRIMARY KEY (' . implode(', ', array_map(fn($c) => "`{$c}`", $cols)) . ')';
        return $this;
    }

    public function unique(string|array $cols, ?string $name = null): self
    {
        $cols = (array)$cols;
        $name = $name ?? 'uniq_' . implode('_', $cols);
        $this->indexes[] = "UNIQUE KEY `{$name}` (" . implode(', ', array_map(fn($c) => "`{$c}`", $cols)) . ')';
        return $this;
    }

    public function index(string|array $cols, ?string $name = null): self
    {
        $cols = (array)$cols;
        $name = $name ?? 'idx_' . implode('_', $cols);
        $this->indexes[] = "KEY `{$name}` (" . implode(', ', array_map(fn($c) => "`{$c}`", $cols)) . ')';
        return $this;
    }

    public function foreign(string $column, string $references, string $onTable, ?string $onDelete = null, ?string $onUpdate = null): self
    {
        $sql = "FOREIGN KEY (`{$column}`) REFERENCES `{$onTable}`(`{$references}`)";
        if ($onDelete) $sql .= " ON DELETE {$onDelete}";
        if ($onUpdate) $sql .= " ON UPDATE {$onUpdate}";
        $this->foreignKeys[] = $sql;
        return $this;
    }

    public function toCreateSQL(): string
    {
        $parts = array_map(fn($c) => (string)$c, $this->columns);
        if ($this->indexes) $parts = array_merge($parts, $this->indexes);
        if ($this->foreignKeys) $parts = array_merge($parts, $this->foreignKeys);

        $body = implode(",\n  ", $parts);
        return "CREATE TABLE IF NOT EXISTS `{$this->table}` (\n  {$body}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }

    public function toAlterSQL(): string
    {
        $adds = array_map(fn($c) => 'ADD ' . (string)$c, $this->columns);
        $body = implode(",\n  ", $adds);
        return "ALTER TABLE `{$this->table}`\n  {$body};";
    }
}
