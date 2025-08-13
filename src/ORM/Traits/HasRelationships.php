<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

use Marwa\DB\ORM\Model;
use Marwa\DB\Connection\ConnectionManager;

trait HasRelationships
{
    protected static ?ConnectionManager $__cm = null;
    protected static string $__conn = 'default';

    public static function setConnectionManager(ConnectionManager $cm, string $conn = 'default'): void
    {
        static::$__cm = $cm;
        static::$__conn = $conn;
    }

    /** @return Model|null */
    protected function hasOne(string $related, string $foreignKey, string $localKey = 'id'): ?Model
    {
        $cm = static::$__cm;
        return $related::query($cm, static::$__conn)->where($foreignKey, '=', $this->getAttribute($localKey))->first()
            ? new $related($related::query($cm, static::$__conn)->where($foreignKey, '=', $this->getAttribute($localKey))->first())
            : null;
    }

    /** @return array<int,Model> */
    protected function hasMany(string $related, string $foreignKey, string $localKey = 'id'): array
    {
        $cm = static::$__cm;
        $rows = $related::query($cm, static::$__conn)->where($foreignKey, '=', $this->getAttribute($localKey))->get();
        return array_map(fn($r) => new $related($r), $rows);
    }

    /** @return Model|null */
    protected function belongsTo(string $related, string $foreignKey, string $ownerKey = 'id'): ?Model
    {
        $cm = static::$__cm;
        $id = $this->getAttribute($foreignKey);
        $row = $related::query($cm, static::$__conn)->where($ownerKey, '=', $id)->first();
        return $row ? new $related($row) : null;
    }
}
