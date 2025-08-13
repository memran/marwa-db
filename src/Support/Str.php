<?php

declare(strict_types=1);

namespace Marwa\DB\Support;

final class Str
{
    public static function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value))));
    }
}
