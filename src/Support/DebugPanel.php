<?php

declare(strict_types=1);

namespace Marwa\DB\Support;

use Marwa\DB\Logger\QueryLogger;

final class DebugPanel
{
    public static function render(QueryLogger $logger): void
    {
        foreach ($logger->all() as $i => $q) {
            $ms = number_format($q['time'] * 1000, 2);
            echo "#{$i} [{$q['conn']}] {$ms} ms  {$q['sql']}\n";
        }
    }
}
