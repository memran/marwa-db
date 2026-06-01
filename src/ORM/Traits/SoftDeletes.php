<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

use Marwa\DB\Support\Helpers;

trait SoftDeletes
{
    protected static bool $softDeletes = false;

    protected static bool $includeTrashed = false;

    protected static bool $onlyTrashed = false;

    public function trashed(): bool
    {
        return !empty($this->attributes['deleted_at']);
    }

    public function delete(): bool
    {
        if (!$this->exists) return false;

        static::fireEvent('deleting', $this);

        if (static::$softDeletes) {
            $data = ['deleted_at' => Helpers::now()];
            $affected = static::baseQuery()
                ->where(static::$primaryKey, '=', $this->getKey())
                ->update($data);
            if ($affected > 0) {
                $this->attributes['deleted_at'] = $data['deleted_at'];
                $this->original['deleted_at']   = $data['deleted_at'];
                static::fireEvent('deleted', $this);
                return true;
            }
            return false;
        }

        $affected = static::baseQuery()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->delete();

        if ($affected > 0) {
            $this->exists = false;
            static::fireEvent('deleted', $this);
            return true;
        }
        return false;
    }

    public function forceDelete(): bool
    {
        if (!$this->exists) return false;
        static::fireEvent('deleting', $this);

        $affected = static::baseQuery()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->delete();

        if ($affected > 0) {
            $this->exists = false;
            return true;
        }
        return false;
    }

    public function restore(): bool
    {
        if (!static::$softDeletes) return false;

        static::fireEvent('restoring', $this);

        $affected = static::baseQuery()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->update(['deleted_at' => null]);

        if ($affected > 0) {
            $this->attributes['deleted_at'] = null;
            $this->original['deleted_at']   = null;
            static::fireEvent('restored', $this);
            return true;
        }
        return false;
    }

    public static function withTrashed(): \Marwa\DB\ORM\QueryBuilder
    {
        return static::query()->withTrashed();
    }

    public static function onlyTrashed(): \Marwa\DB\ORM\QueryBuilder
    {
        return static::query()->onlyTrashed();
    }
}
