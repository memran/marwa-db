<?php

declare(strict_types=1);

namespace Marwa\DB\ORM;

use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Traits\Timestamps;
use Marwa\DB\ORM\Traits\SoftDeletes;
use Marwa\DB\ORM\Traits\MassAssignment;
use Marwa\DB\ORM\Traits\CastsAttributes;
use Marwa\DB\ORM\Traits\HasRelationships;
use Marwa\DB\ORM\Traits\EagerLoads;
use Marwa\DB\ORM\Traits\HasAttributes;
use Marwa\DB\ORM\Traits\HasQuery;
use Marwa\DB\ORM\Traits\HasState;
use Marwa\DB\ORM\Traits\Observable;

/** @phpstan-consistent-constructor */
abstract class Model
{
    use Timestamps, SoftDeletes, MassAssignment, CastsAttributes, HasAttributes, HasRelationships, EagerLoads, Observable, HasQuery, HasState;

    /** @var array<class-string,\Closure> */
    protected static array $globalScopes = [];
    /** Table + key */
    protected static ?string $table = null; // if null, it will be inferred
    protected static string $primaryKey = 'id';

    /** Connection (shared by all models) */
    protected static ?ConnectionManager $cm = null;
    protected static string $connection = 'default';

    /** State */
    /** @var array<string,mixed> */
    protected array $attributes = [];
    /** @var array<string,mixed> */
    protected array $original = [];
    protected bool $exists = false;

    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(array $attributes = [], bool $exists = false)
    {
        $this->attributes = $attributes;
        $this->original   = $attributes;
        $this->exists     = $exists;

        $this->boot();
    }

    public function boot(): void {}
    /**
     * Get the fully-resolved table name for this model.
     * If not set, infer from the class name and cache it in static::$table.
     */
    public static function table(): string
    {
        if (static::$table !== null && static::$table !== '') {
            return static::$table;
        }
        static::$table = static::inferTableName();
        return static::$table;
    }

    /**
     * Explicitly set the model table name (overrides inference).
     */
    public static function setTable(string $table): void
    {
        static::$table = $table;
    }


    /** Wire a ConnectionManager for all models */
    public static function setConnectionManager(ConnectionManager $cm, string $connection = 'default'): void
    {
        static::$cm = $cm;
        static::$connection = $connection;
    }

    /** Model‑aware query builder (hydrates to models, supports eager loading) */
    public static function query(): \Marwa\DB\ORM\QueryBuilder
    {
        if (!static::$cm) {
            throw new \RuntimeException('ConnectionManager not set. Call Model::setConnectionManager().');
        }
        /** @var class-string<static> $cls */
        $cls = static::class;
        return new \Marwa\DB\ORM\QueryBuilder(static::$cm, $cls, static::$connection);
    }

    /**
     * Add a global scope (applies to all queries).
     * Signature: function($builder): void
     */
    public static function addGlobalScope(\Closure $scope, ?string $identifier = null): void
    {
        $id = $identifier ?? spl_object_hash($scope);
        $cls = static::class;
        if (!isset(static::$globalScopes[$cls])) {
            static::$globalScopes[$cls] = [];
        }
        static::$globalScopes[$cls][$id] = $scope;
    }

    /**
     * Remove a global scope by identifier (only for next query).
     */
    public static function withoutGlobalScope(string $identifier): static
    {
        $instance = new static();
        $instance->disableGlobalScope($identifier);
        return $instance;
    }

    /** Base low‑level builder for internal writes */
    protected static function baseQuery(): \Marwa\DB\Query\Builder
    {
        if (!static::$cm) {
            throw new \RuntimeException('ConnectionManager not set. Call Model::setConnectionManager().');
        }
        $model = new static();
        // Apply active global scopes
        $builder = (new \Marwa\DB\Query\Builder(static::$cm, static::$connection))->table(static::table());
        $scopes = static::$globalScopes[static::class] ?? [];
        /** @var \Closure(\Marwa\DB\Query\Builder):void $scope */
        foreach ($scopes as $id => $scope) {
            if (!isset($model->disabledGlobalScopes[$id])) {
                $scope($builder);
            }
        }
        return $builder;
    }




    /** Apply default soft‑delete filter to a low‑level builder (if available) */
    protected static function applySoftDeleteFilter(\Marwa\DB\Query\Builder $qb): \Marwa\DB\Query\Builder
    {
        if (!static::$softDeletes) {
            return $qb;
        }
        if (static::$onlyTrashed) {
            // requires whereNotNull in low-level Builder (recommended)
            if (method_exists($qb, 'whereNotNull')) {
                $qb->whereNotNull('deleted_at');
            }
        } elseif (!static::$includeTrashed) {
            if (method_exists($qb, 'whereNull')) {
                $qb->whereNull('deleted_at');
            }
        }
        // reset flags for next call
        static::$includeTrashed = false;
        static::$onlyTrashed = false;
        return $qb;
    }

    /** ===== Mutators ===== */

    /** Mass create + persist (fillable + timestamps)
     * @param array<string,mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        $instance = new static();
        /** @var array<string,mixed> $data */
        $data = static::filterFillable($attributes);

        static::fireEvent('creating', $instance);

        if (static::$timestamps) {
            $instance->touchTimestamps($data);
        }

        static::baseQuery()->insert($data);

        $model = $instance;
        $id = static::$cm?->getPdo(static::$connection)->lastInsertId();
        if ($id && is_numeric($id)) {
            $fresh = static::find((int)$id);
            if ($fresh) {
                $model = $fresh;
            }
        }

        if ($model === $instance) {
            $model = new static($data, true);
        }

        unset($instance);
        static::fireEvent('created', $model);
        return $model;
    }

    /** Insert or update this instance */
    public function save(): bool
    {
        $data = $this->getDirty();
        if (static::$timestamps) {
            $this->touchTimestamps($data);
        }

        if ($this->exists) {
            if (!$data) return true;
            static::fireEvent('updating', $this);
            $affected = static::baseQuery()
                ->where(static::$primaryKey, '=', $this->getKey())
                ->update($data);
            if ($affected > 0) {
                $this->original = array_replace($this->original, $data);
                $this->attributes = array_replace($this->attributes, $data);
                static::fireEvent('updated', $this);
                static::fireEvent('saved', $this);
                return true;
            }
            return false;
        }

        // Insert path
        static::fireEvent('saving', $this);
        $insertData = $this->attributes + $data;
        if (static::$timestamps) {
            $this->touchTimestamps($insertData);
        }

        static::baseQuery()->insert($insertData);

        $id = static::$cm?->getPdo(static::$connection)->lastInsertId();
        if ($id && is_numeric($id)) {
            $this->attributes[static::$primaryKey] = (int)$id;
        }
        $this->original = $this->attributes;
        $this->exists = true;
        static::fireEvent('saved', $this);
        return true;
    }

    /**
     * Get the primary key column name for the model.
     *
     * @return string
     */
    public function getPrimaryKey(): string
    {
        if (static::$primaryKey !== '') {
            return static::$primaryKey;
        }

        return 'id';
    }
    /**
     * Destroy one or multiple records by primary key.
     *
     * @param int|array<int> $ids Single ID or array of IDs
     * @return int Number of deleted (or soft deleted) records
     */
    public static function destroy(int|array $ids): int
    {
        $instance = new static();
        $pk       = $instance->getPrimaryKey();
        $ids      = is_array($ids) ? $ids : [$ids];
        $qb       = $instance->baseQuery()->whereIn($pk, $ids);

        // Soft delete enabled → update deleted_at timestamp
        if (static::$softDeletes) {
            return $qb->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        // Hard delete
        return $qb->delete();
    }

    /** ===== Accessors / Serialization ===== */

    public function getKey(): int|string|null
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    public function getKeyName(): string
    {
        return static::$primaryKey;
    }

    /** Hydrate a row into a model instance marked as existing */
    /** @param array<string,mixed>|object $row */
    protected static function hydrateRow(array|object $row): static
    {
        $data = is_array($row) ? $row : (array)$row;
        return new static($data, true);
    }
    /**
     * Infer table from short class name: `App\Models\UserProfile` -> `user_profiles`
     * Rules:
     *  - CamelCase → snake_case
     *  - Naive pluralization: words ending in (s,x,z,ch,sh) -> +es; word ending in 'y' after a consonant -> 'ies'; default -> +s
     *  - Optional small irregulars map (you can extend it)
     */
    protected static function inferTableName(): string
    {
        $base = static::classBaseName();        // e.g., "UserProfile"
        $snake = static::toSnakeCase($base);    // "user_profile"
        return static::pluralize($snake);       // "user_profiles"
    }

    /** "App\Models\UserProfile" -> "UserProfile" */
    protected static function classBaseName(): string
    {
        $fqcn = static::class;
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    /** "UserProfile" -> "user_profile" */
    protected static function toSnakeCase(string $name): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);
        return strtolower($snake ?? $name);
    }

    /** Very small, dependency‑free pluralizer for table names */
    protected static function pluralize(string $word): string
    {
        // Irregulars (extend as needed)
        static $irregular = [
            'person' => 'people',
            'man'    => 'men',
            'woman'  => 'women',
            'child'  => 'children',
            'tooth'  => 'teeth',
            'foot'   => 'feet',
            'mouse'  => 'mice',
            'goose'  => 'geese',
        ];

        if (isset($irregular[$word])) {
            return $irregular[$word];
        }

        // if already “looks plural”, keep as-is (basic heuristic)
        if (preg_match('/s$/', $word)) {
            return $word;
        }

        // y -> ies (only if preceded by a consonant)
        if (preg_match('/[^aeiou]y$/i', $word)) {
            return preg_replace('/y$/i', 'ies', $word);
        }

        // es endings
        if (preg_match('/(s|x|z|ch|sh)$/i', $word)) {
            return $word . 'es';
        }

        // default: +s
        return $word . 's';
    }

    /**
     * Dynamically handle calls to the builder (local scopes).
     */
    /** @param array<mixed> $parameters */
    public function __call(string $method, array $parameters): mixed
    {
        $scope = 'scope' . ucfirst($method);

        if (method_exists($this, $scope)) {
            $builder = $this->baseQuery();
            return $this->{$scope}($builder, ...$parameters);
        }

        throw new \InvalidArgumentException("Method {$method} does not exist.");
    }

    /**
     * For static calls like User::active().
     */
    /** @param array<mixed> $parameters */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        $instance = new static();
        $scope = 'scope' . ucfirst($method);

        if (method_exists($instance, $scope)) {
            $builder = $instance->baseQuery();
            return $instance->{$scope}($builder, ...$parameters);
        }

        throw new \InvalidArgumentException("Static method {$method} does not exist.");
    }

    /* -----------------------------------------------------------------
     | Internal Scope State
     |------------------------------------------------------------------*/

    /** @var array<string,bool> */
    protected array $disabledGlobalScopes = [];

    protected function disableGlobalScope(string $identifier): void
    {
        $this->disabledGlobalScopes[$identifier] = true;
    }
}
