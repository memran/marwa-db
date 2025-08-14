<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

final class ColumnDefinition
{
    private ?Blueprint $blueprint = null;

    public function __construct(
        public string $type,
        public string $name,
        public array  $attributes = []
    ) {}

    /** Internal: set by Blueprint when the column is added */
    public function setBlueprint(Blueprint $b): void
    {
        $this->blueprint = $b;
    }

    // ---- Existing modifiers
    public function nullable(bool $flag = true): self
    {
        $this->attributes['nullable'] = $flag;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->attributes['default'] = $value;
        return $this;
    }

    public function unsigned(bool $flag = true): self
    {
        $this->attributes['unsigned'] = $flag;
        return $this;
    }

    public function autoIncrement(bool $flag = true): self
    {
        $this->attributes['autoIncrement'] = $flag;
        return $this;
    }

    public function primary(bool $flag = true): self
    {
        $this->attributes['primary'] = $flag;
        return $this;
    }

    public function length(int $len): self
    {
        $this->attributes['length'] = $len;
        return $this;
    }

    public function comment(string $c): self
    {
        $this->attributes['comment'] = $c;
        return $this;
    }

    // ---- NEW fluent index helpers (table-index level)
    public function unique(?string $name = null): self
    {
        // Register a unique index for this single column at the blueprint level
        $this->blueprint->indexes[] = [
            'type'    => 'unique',
            'columns' => [$this->name],
            'name'    => $name,
        ];
        return $this;
    }

    public function index(?string $name = null): self
    {
        // Register a non-unique index for this single column
        $this->blueprint->indexes[] = [
            'type'    => 'index',
            'columns' => [$this->name],
            'name'    => $name,
        ];
        return $this;
    }

    /**
     * For a column-level PK declaration when you prefer a separate PRIMARY KEY constraint
     * instead of inline `PRIMARY KEY` attribute.
     */
    public function primaryKey(?string $name = null): self
    {
        $this->blueprint->commands[] = [
            'type'    => 'primary',
            'columns' => [$this->name],
            'name'    => $name,
        ];
        return $this;
    }
}
