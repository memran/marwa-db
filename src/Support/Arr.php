<?php

declare(strict_types=1);

namespace Marwa\DB\Support;

final class Arr
{
    public static function get(array $arr, string $key, mixed $default = null): mixed
    {
        return $arr[$key] ?? $default;
    }
}
