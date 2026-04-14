<?php

declare(strict_types=1);

namespace Marwa\DB\Support;

use Marwa\DB\Connection\ConnectionManager;

final class Helpers
{
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists(__NAMESPACE__ . '\\db_debugbar')) {
    function db_debugbar(?ConnectionManager $cm = null): string
    {
        $manager = $cm;

        if ($manager === null) {
            $global = $GLOBALS['cm'] ?? null;
            $manager = $global instanceof ConnectionManager ? $global : null;
        }

        return $manager?->renderDebugBar() ?? '';
    }
}
