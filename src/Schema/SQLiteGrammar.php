<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

final class SQLiteGrammar implements Grammar
{
    public function compileCreate(Blueprint $b): array
    {
        $cols = array_map(fn($c) => $this->columnSql($c), $b->columns);

        // Inline primary keys & basic unique indexes
        foreach ($b->commands as $cmd) {
            if ($cmd['type'] === 'primary') {
                $cols[] = 'PRIMARY KEY (' . $this->wrapList($cmd['columns']) . ')';
            }
            // SQLite foreign keys supported if PRAGMA foreign_keys=ON (assume on); add for tests
            if ($cmd['type'] === 'foreign') {
                $cols[] = $this->foreignSql($cmd);
            }
        }

        $sql[] = sprintf('CREATE TABLE %s (%s)', $this->wrap($b->table), implode(', ', $cols));

        // indexes must be separate statements in SQLite
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
        // SQLite cannot easily add FK/PK via ALTER; skip for testing simplicity.
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

    public function compileDrop(string $table): array
    {
        return [sprintf('DROP TABLE IF EXISTS %s', $this->wrap($table))];
    }

    public function compileRename(string $from, string $to): array
    {
        return [sprintf('ALTER TABLE %s RENAME TO %s', $this->wrap($from), $this->wrap($to))];
    }

    // ---- helpers

    private function columnSql(ColumnDefinition $c): string
    {
        // SQLite is dynamic typed; map to closest affinities for tests
        $type = match ($c->type) {
            'uuid'        => 'TEXT',
            'string'      => 'VARCHAR(' . ($c->attributes['length'] ?? 255) . ')',
            'text'        => 'TEXT',
            'mediumText'  => 'TEXT',
            'longText'    => 'TEXT',
            'tinyInteger',
            'smallInteger',
            'integer'     => 'INTEGER',
            'bigInteger',
            'bigint'      => 'INTEGER',
            'boolean'     => 'INTEGER',
            'decimal',
            'float',
            'double'      => 'REAL',
            'date'        => 'TEXT',
            'dateTime'    => 'TEXT',
            'timestamp'   => 'TEXT',
            'json',
            'jsonb'       => 'TEXT',
            'binary'      => 'BLOB',
            'enum',
            'set'         => 'TEXT',
            default       => strtoupper($c->type),
        };

        $sql = $this->wrap($c->name) . ' ' . $type;

        // In SQLite, AUTOINCREMENT only on INTEGER PRIMARY KEY; we approximate
        if (!empty($c->attributes['autoIncrement'])) {
            // handled if primary added; leave type as INTEGER
        }

        if (!empty($c->attributes['primary'])) {
            $sql .= ' PRIMARY KEY';
            if (!empty($c->attributes['autoIncrement'])) {
                $sql .= ' AUTOINCREMENT';
            }
        }

        if (!empty($c->attributes['nullable'])) {
            // default is nullable in SQLite; only add NOT NULL when needed
        } else {
            $sql .= ' NOT NULL';
        }

        if (array_key_exists('default', $c->attributes)) {
            $def = $c->attributes['default'];
            $sql .= ' DEFAULT ' . $this->literal($def);
        }

        return $sql;
    }

    private function foreignSql(array $cmd): string
    {
        $cols = $this->wrapList($cmd['columns']);
        $refT = $this->wrap($cmd['options']['on']);
        $refC = $this->wrapList($cmd['options']['references']);
        $onDelete = $cmd['options']['onDelete'] ?? null;
        $onUpdate = $cmd['options']['onUpdate'] ?? null;

        $sql = 'FOREIGN KEY (' . $cols . ') REFERENCES ' . $refT . ' (' . $refC . ')';
        if ($onDelete) $sql .= ' ON DELETE ' . strtoupper($onDelete);
        if ($onUpdate) $sql .= ' ON UPDATE ' . strtoupper($onUpdate);
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
        if ($val === null) return 'NULL';
        if (is_bool($val)) return $val ? '1' : '0';
        if (is_numeric($val)) return (string)$val;
        return "'" . str_replace("'", "''", (string)$val) . "'";
    }
}
