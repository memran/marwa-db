<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

final class Blueprint
{
    public const MODE_CREATE = 'create';
    public const MODE_ALTER  = 'alter';

    private string $mode = self::MODE_CREATE;

    /** @var ColumnDefinition[] */
    public array $columns = [];

    /** @var array<int, array{type:string,columns:string[],name?:string,options?:array}> */
    public array $indexes = [];

    /** @var array<int, array{type:string,columns:string[],name?:string,options?:array}> */
    public array $commands = []; // misc commands (primary/foreign in CREATE, etc.)

    public function __construct(public string $table) {}

    public function setTableMode(string $mode): void
    {
        $this->mode = $mode;
    }
    public function mode(): string
    {
        return $this->mode;
    }

    // ---- Column factories (common “all types” you’ll want in tests)

    public function increments(string $name = 'id'): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('integer', $name, ['autoIncrement' => true, 'unsigned' => true, 'primary' => true]));
    }

    public function bigIncrements(string $name = 'id'): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('bigint', $name, ['autoIncrement' => true, 'unsigned' => true, 'primary' => true]));
    }

    public function uuid(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('uuid', $name));
    }

    public function uuidPrimary(string $name = 'id'): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('uuid', $name, ['primary' => true]));
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('string', $name, ['length' => $length]));
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('text', $name));
    }

    public function mediumText(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('mediumText', $name));
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('longText', $name));
    }

    public function integer(string $name, bool $unsigned = false): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('integer', $name, ['unsigned' => $unsigned]));
    }

    public function tinyInteger(string $name, bool $unsigned = false): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('tinyInteger', $name, ['unsigned' => $unsigned]));
    }

    public function smallInteger(string $name, bool $unsigned = false): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('smallInteger', $name, ['unsigned' => $unsigned]));
    }

    public function bigInteger(string $name, bool $unsigned = false): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('bigInteger', $name, ['unsigned' => $unsigned]));
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('boolean', $name));
    }

    public function decimal(string $name, int $precision = 10, int $scale = 0, bool $unsigned = false): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('decimal', $name, ['precision' => $precision, 'scale' => $scale, 'unsigned' => $unsigned]));
    }

    public function float(string $name, int $precision = 10, int $scale = 2): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('float', $name, ['precision' => $precision, 'scale' => $scale]));
    }

    public function double(string $name, int $precision = 15, int $scale = 8): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('double', $name, ['precision' => $precision, 'scale' => $scale]));
    }

    public function date(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('date', $name));
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('dateTime', $name));
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('timestamp', $name));
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes(string $column = 'deleted_at'): ColumnDefinition
    {
        return $this->timestamp($column)->nullable();
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('json', $name));
    }

    public function jsonb(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('jsonb', $name));
    }

    public function binary(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('binary', $name));
    }

    public function enum(string $name, array $allowed): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('enum', $name, ['allowed' => $allowed]));
    }

    public function set(string $name, array $allowed): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('set', $name, ['allowed' => $allowed]));
    }

    public function foreignId(string $name): ColumnDefinition
    {
        return $this->add(new ColumnDefinition('bigint', $name, ['unsigned' => true]));
    }

    // ---- Index / Keys

    public function primary(array|string $columns, ?string $name = null): void
    {
        $this->commands[] = ['type' => 'primary', 'columns' => (array)$columns, 'name' => $name];
    }

    public function unique(array|string $columns, ?string $name = null): void
    {
        $this->indexes[] = ['type' => 'unique', 'columns' => (array)$columns, 'name' => $name];
    }

    public function index(array|string $columns, ?string $name = null): void
    {
        $this->indexes[] = ['type' => 'index', 'columns' => (array)$columns, 'name' => $name];
    }

    public function foreign(array|string $columns, string $refTable, array|string $refColumns = 'id', ?string $name = null, array $options = []): void
    {
        $this->commands[] = [
            'type'     => 'foreign',
            'columns'  => (array)$columns,
            'name'     => $name,
            'options'  => array_merge(['references' => (array)$refColumns, 'on' => $refTable], $options),
        ];
    }

    // ---- internal

    private function add(ColumnDefinition $col): ColumnDefinition
    {
        $col->setBlueprint($this);
        $this->columns[] = $col;
        return $col;
    }
}
