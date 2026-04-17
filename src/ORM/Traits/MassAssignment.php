<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

trait MassAssignment
{
    /** @var array<string> */
    protected static array $fillable = [];
    /** @var array<string> */
    protected static array $guarded  = ['*'];

    /** @param array<mixed> $data */
    protected static function filterFillable(array $data): array
    {
        if (static::$fillable) {
            return array_intersect_key($data, array_flip(static::$fillable));
        }
        if (static::$guarded === ['*']) {
            return [];
        }
        return array_diff_key($data, array_flip(static::$guarded));
    }
}
