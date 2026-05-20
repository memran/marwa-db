<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

use Marwa\Support\Json;

trait CastsAttributes
{
    /** @var array<string,string> */
    protected static array $casts = [];

    /** @return array<string,string> */
    public function casts(): array
    {
        return static::$casts;
    }

    public function castIn(string $key, mixed $val): mixed
    {
        return match (static::$casts[$key] ?? null) {
            'json'  => is_array($val) ? Json::encode($val) : $val,
            default => $val,
        };
    }

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

    /** @param array<string,string> $casts */
    public function mergeCasts(array $casts): static
    {
        static::$casts = array_merge(static::$casts, $casts);

        return $this;
    }
}
