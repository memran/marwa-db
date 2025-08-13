<?php

declare(strict_types=1);

namespace Marwa\DB\Support;

final class Helpers
{
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
