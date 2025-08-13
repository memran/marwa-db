<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

final class ColumnDefinition
{
    public function __construct(private string $sql) {}

    public function nullable(): self
    {
        $this->sql .= ' NULL';
        return $this;
    }
    public function notNullable(): self
    {
        $this->sql .= ' NOT NULL';
        return $this;
    }

    public function default(string|int|float|bool|null $value): self
    {
        if ($value === null) {
            $this->sql .= ' DEFAULT NULL';
        } elseif (is_bool($value)) {
            $this->sql .= ' DEFAULT ' . ($value ? '1' : '0');
        } elseif (is_numeric($value)) {
            $this->sql .= ' DEFAULT ' . $value;
        } else {
            $this->sql .= " DEFAULT '" . addslashes($value) . "'";
        }
        return $this;
    }

    public function after(string $column): self
    {
        $this->sql .= " AFTER `{$column}`";
        return $this;
    }

    public function __toString(): string
    {
        return $this->sql;
    }
}
