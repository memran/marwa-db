<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

final class MySqlGrammar implements Grammar
{
    public function compileCreate(Blueprint $b): array
    {
        $cols = array_map(fn($c) => $this->columnSql($c), $b->columns);
        // inline primary keys in column defs; collect additional PKs and FKs
        foreach ($b->commands as $cmd) {
            if ($cmd['type'] === 'primary') {
                $cols[] = 'PRIMARY KEY (' . $this->wrapList($cmd['columns']) . ')';
            } elseif ($cmd['type'] === 'foreign') {
                $cols[] = $this->foreignSql($cmd);
            }
        }
        foreach ($b->indexes as $idx) {
            if ($idx['type'] === 'unique') {
                $name = $idx['name'] ?? ('uniq_' . $b->table . '_' . implode('_', $idx['columns']));
                $cols[] = 'UNIQUE KEY ' . $this->wrap($name) . ' (' . $this->wrapList($idx['columns']) . ')';
            } elseif ($idx['type'] === 'index') {
                $name = $idx['name'] ?? ('idx_' . $b->table . '_' . implode('_', $idx['columns']));
                $cols[] = 'KEY ' . $this->wrap($name) . ' (' . $this->wrapList($idx['columns']) . ')';
            }
        }

        $sql = sprintf(
            'CREATE TABLE %s (%s) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $this->wrap($b->table),
            implode(', ', $cols)
        );
        return [$sql];
    }

    public function compileTable(Blueprint $b): array
    {
        $sql = [];
        foreach ($b->columns as $c) {
            $sql[] = sprintf('ALTER TABLE %s ADD %s', $this->wrap($b->table), $this->columnSql($c));
        }
        foreach ($b->indexes as $idx) {
            if ($idx['type'] === 'unique') {
                $name = $idx['name'] ?? ('uniq_' . $b->table . '_' . implode('_', $idx['columns']));
                $sql[] = sprintf('ALTER TABLE %s ADD UNIQUE %s (%s)', $this->wrap($b->table), $this->wrap($name), $this->wrapList($idx['columns']));
            } else {
                $name = $idx['name'] ?? ('idx_' . $b->table . '_' . implode('_', $idx['columns']));
                $sql[] = sprintf('ALTER TABLE %s ADD INDEX %s (%s)', $this->wrap($b->table), $this->wrap($name), $this->wrapList($idx['columns']));
            }
        }
        foreach ($b->commands as $cmd) {
            if ($cmd['type'] === 'primary') {
                $sql[] = sprintf('ALTER TABLE %s ADD PRIMARY KEY (%s)', $this->wrap($b->table), $this->wrapList($cmd['columns']));
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
        return [sprintf('RENAME TABLE %s TO %s', $this->wrap($from), $this->wrap($to))];
    }

    // ---- helpers

    private function columnSql(ColumnDefinition $c): string
    {
        $type = match ($c->type) {
            'uuid'        => 'CHAR(36)',
            'string'      => 'VARCHAR(' . ($c->attributes['length'] ?? 255) . ')',
            'text'        => 'TEXT',
            'mediumText'  => 'MEDIUMTEXT',
            'longText'    => 'LONGTEXT',
            'tinyInteger' => ($c->attributes['unsigned'] ?? false) ? 'TINYINT UNSIGNED' : 'TINYINT',
            'smallInteger' => ($c->attributes['unsigned'] ?? false) ? 'SMALLINT UNSIGNED' : 'SMALLINT',
            'integer'     => ($c->attributes['unsigned'] ?? false) ? 'INT UNSIGNED'     : 'INT',
            'bigInteger'  => ($c->attributes['unsigned'] ?? false) ? 'BIGINT UNSIGNED'  : 'BIGINT',
            'bigint'      => ($c->attributes['unsigned'] ?? false) ? 'BIGINT UNSIGNED'  : 'BIGINT',
            'boolean'     => 'TINYINT(1)',
            'decimal'     => 'DECIMAL(' . ($c->attributes['precision'] ?? 10) . ',' . ($c->attributes['scale'] ?? 0) . ')'
                . (($c->attributes['unsigned'] ?? false) ? ' UNSIGNED' : ''),
            'float'       => 'FLOAT(' . ($c->attributes['precision'] ?? 10) . ',' . ($c->attributes['scale'] ?? 2) . ')',
            'double'      => 'DOUBLE(' . ($c->attributes['precision'] ?? 15) . ',' . ($c->attributes['scale'] ?? 8) . ')',
            'date'        => 'DATE',
            'dateTime'    => 'DATETIME',
            'timestamp'   => 'TIMESTAMP',
            'json'        => 'JSON',
            'jsonb'       => 'JSON', // MySQL has only JSON
            'binary'      => 'BLOB',
            'enum'        => 'ENUM(' . $this->quoteList($c->attributes['allowed'] ?? []) . ')',
            'set'         => 'SET(' . $this->quoteList($c->attributes['allowed'] ?? []) . ')',
            default       => strtoupper($c->type),
        };

        $sql = $this->wrap($c->name) . ' ' . $type;

        if (!empty($c->attributes['autoIncrement'])) {
            $sql .= ' AUTO_INCREMENT';
        }
        if (!empty($c->attributes['nullable'])) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }
        if (array_key_exists('default', $c->attributes)) {
            $def = $c->attributes['default'];
            $sql .= ' DEFAULT ' . $this->literal($def);
        }
        if (!empty($c->attributes['primary'])) {
            $sql .= ' PRIMARY KEY';
        }
        if (!empty($c->attributes['comment'])) {
            $sql .= ' COMMENT ' . $this->literal($c->attributes['comment']);
        }

        return $sql;
    }

    private function foreignSql(array $cmd): string
    {
        $name = $cmd['name'] ?? ('fk_' . uniqid());
        $cols = $this->wrapList($cmd['columns']);
        $refT = $this->wrap($cmd['options']['on']);
        $refC = $this->wrapList($cmd['options']['references']);
        $onDelete = $cmd['options']['onDelete'] ?? null;
        $onUpdate = $cmd['options']['onUpdate'] ?? null;

        $sql = 'CONSTRAINT ' . $this->wrap($name) . ' FOREIGN KEY (' . $cols . ') REFERENCES ' . $refT . ' (' . $refC . ')';
        if ($onDelete) $sql .= ' ON DELETE ' . strtoupper($onDelete);
        if ($onUpdate) $sql .= ' ON UPDATE ' . strtoupper($onUpdate);
        return $sql;
    }

    private function wrap(string $id): string
    {
        return '`' . str_replace('`', '``', $id) . '`';
    }

    private function wrapList(array $ids): string
    {
        return implode(', ', array_map(fn($c) => $this->wrap($c), $ids));
    }

    private function quoteList(array $vals): string
    {
        return implode(', ', array_map(fn($v) => $this->literal($v), $vals));
    }

    private function literal(mixed $val): string
    {
        if ($val === null) return 'NULL';
        if (is_bool($val)) return $val ? '1' : '0';
        if (is_numeric($val)) return (string)$val;
        return "'" . str_replace("'", "''", (string)$val) . "'";
    }
}
