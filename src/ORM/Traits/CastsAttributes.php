<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

use Marwa\Support\Json;

trait CastsAttributes
{
    /** @var array<string,string> */
    protected static array $casts = [];

    protected function castOut(string $key, mixed $val): mixed
    {
        return match (static::$casts[$key] ?? null) {
            'int'   => (int)$val,
            'float' => (float)$val,
            'bool'  => (bool)$val,
            'json'  => is_string($val) ? Json::decode($val) : $val,
            default => $val,
        };
    }
}
