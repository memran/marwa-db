<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

final class PgSqlGrammar implements Grammar
{
    public function compileCreate(Blueprint $b): array
    {
        $cols = array_map(fn($c) => $this->columnSql($c), $b->columns);

        foreach ($b->commands as $cmd) {
            if ($cmd['type'] === 'primary') {
                $cols[] = 'PRIMARY KEY (' . $this->wrapList($cmd['columns']) . ')';
            } elseif ($cmd['type'] === 'foreign') {
                $cols[] = $this->foreignSql($cmd);
            }
        }

        $sql = [sprintf('CREATE TABLE %s (%s)', $this->wrap($b->table), implode(', ', $cols))];

        foreach ($b->indexes as $idx) {
            $name = $idx['name'] ?? ('idx_' . $b->table . '_' . implode('_', $idx['columns']));
            if ($idx['type'] === 'unique') {
                $sql[] = sprintf(
                    'CREATE UNIQUE INDEX %s ON %s (%s)',
                    $this->wrap($name),
                    $this->wrap($b->table),
                    $this->wrapList($idx['columns'])
                );
            } else {
                $sql[] = sprintf(
                    'CREATE INDEX %s ON %s (%s)',
                    $this->wrap($name),
                    $this->wrap($b->table),
                    $this->wrapList($idx['columns'])
                );
            }
        }

        return $sql;
    }

    public function compileTable(Blueprint $b): array
    {
        $sql = [];

        foreach ($b->columns as $c) {
            $sql[] = sprintf('ALTER TABLE %s ADD COLUMN %s', $this->wrap($b->table), $this->columnSql($c));
        }

        foreach ($b->indexes as $idx) {
            $name = $idx['name'] ?? ('idx_' . $b->table . '_' . implode('_', $idx['columns']));
            if ($idx['type'] === 'unique') {
                $sql[] = sprintf(
                    'CREATE UNIQUE INDEX %s ON %s (%s)',
                    $this->wrap($name),
                    $this->wrap($b->table),
                    $this->wrapList($idx['columns'])
                );
            } else {
                $sql[] = sprintf(
                    'CREATE INDEX %s ON %s (%s)',
                    $this->wrap($name),
                    $this->wrap($b->table),
                    $this->wrapList($idx['columns'])
                );
            }
        }

        foreach ($b->commands as $cmd) {
            if ($cmd['type'] === 'primary') {
                $name = $cmd['name'] ?? ('pk_' . $b->table . '_' . implode('_', $cmd['columns']));
                $sql[] = sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s PRIMARY KEY (%s)',
                    $this->wrap($b->table),
                    $this->wrap($name),
                    $this->wrapList($cmd['columns'])
                );
            } elseif ($cmd['type'] === 'foreign') {
                $sql[] = sprintf('ALTER TABLE %s ADD %s', $this->wrap($b->table), $this->foreignSql($cmd));
            }
        }

        return $sql;
    }

    public function compileDrop(string $table): array
    {
        return [sprintf('DROP TABLE IF EXISTS %s', $this->wrap($table))];
    }

    public function compileRename(string $from, string $to): array
    {
        return [sprintf('ALTER TABLE %s RENAME TO %s', $this->wrap($from), $this->wrap($to))];
    }

    private function columnSql(ColumnDefinition $c): string
    {
        if (!empty($c->attributes['autoIncrement']) && !empty($c->attributes['primary'])) {
            $type = match ($c->type) {
                'bigInteger', 'bigint' => 'BIGSERIAL',
                default => 'SERIAL',
            };
        } else {
            $type = match ($c->type) {
                'uuid' => 'UUID',
                'string' => 'VARCHAR(' . ($c->attributes['length'] ?? 255) . ')',
                'text' => 'TEXT',
                'mediumText', 'longText' => 'TEXT',
                'tinyInteger' => 'SMALLINT',
                'smallInteger' => 'SMALLINT',
                'integer' => 'INTEGER',
                'bigInteger', 'bigint' => 'BIGINT',
                'boolean' => 'BOOLEAN',
                'decimal' => 'DECIMAL(' . ($c->attributes['precision'] ?? 10) . ',' . ($c->attributes['scale'] ?? 0) . ')',
                'float' => 'REAL',
                'double' => 'DOUBLE PRECISION',
                'date' => 'DATE',
                'dateTime' => 'TIMESTAMP',
                'timestamp' => 'TIMESTAMP',
                'json' => 'JSON',
                'jsonb' => 'JSONB',
                'binary' => 'BYTEA',
                'enum', 'set' => 'TEXT',
                default => strtoupper($c->type),
            };
        }

        $sql = $this->wrap($c->name) . ' ' . $type;

        if (!empty($c->attributes['nullable'])) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        if (array_key_exists('default', $c->attributes)) {
            $sql .= ' DEFAULT ' . $this->literal($c->attributes['default']);
        }

        if (!empty($c->attributes['primary'])) {
            $sql .= ' PRIMARY KEY';
        }

        return $sql;
    }

    private function foreignSql(array $cmd): string
    {
        $name = $cmd['name'] ?? ('fk_' . $cmd['options']['on'] . '_' . implode('_', $cmd['columns']));
        $cols = $this->wrapList($cmd['columns']);
        $refT = $this->wrap($cmd['options']['on']);
        $refC = $this->wrapList($cmd['options']['references']);
        $onDelete = $cmd['options']['onDelete'] ?? null;
        $onUpdate = $cmd['options']['onUpdate'] ?? null;

        $sql = 'CONSTRAINT ' . $this->wrap($name) . ' FOREIGN KEY (' . $cols . ') REFERENCES ' . $refT . ' (' . $refC . ')';
        if ($onDelete) {
            $sql .= ' ON DELETE ' . strtoupper($onDelete);
        }
        if ($onUpdate) {
            $sql .= ' ON UPDATE ' . strtoupper($onUpdate);
        }

        return $sql;
    }

    private function wrap(string $id): string
    {
        return '"' . str_replace('"', '""', $id) . '"';
    }

    private function wrapList(array $ids): string
    {
        return implode(', ', array_map(fn($c) => $this->wrap($c), $ids));
    }

    private function literal(mixed $val): string
    {
        if ($val === null) {
            return 'NULL';
        }
        if (is_bool($val)) {
            return $val ? 'TRUE' : 'FALSE';
        }
        if (is_numeric($val)) {
            return (string) $val;
        }

        return "'" . str_replace("'", "''", (string) $val) . "'";
    }
}
