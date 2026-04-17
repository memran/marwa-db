<?php

declare(strict_types=1);

namespace Marwa\DB\Support;

use Psr\Log\LoggerInterface;

final class DebugBarAdapter
{
    public static function createDefault(?LoggerInterface $logger = null): ?object
    {
        $class = 'Marwa\\DebugBar\\DebugBar';

        if (!class_exists($class)) {
            return null;
        }

        $debugBar = new $class(true);

        if (method_exists($debugBar, 'setLogger') && $logger !== null) {
            $debugBar->setLogger($logger);
        }

        $collectors = method_exists($debugBar, 'collectors') ? $debugBar->collectors() : null;
        $collectorClass = 'Marwa\\DebugBar\\Collectors\\DbQueryCollector';

        if ($collectors !== null && class_exists($collectorClass) && method_exists($collectors, 'register')) {
            $collectors->register($collectorClass);
        }

        return self::supports($debugBar) ? $debugBar : null;
    }

    public static function supports(?object $debugBar): bool
    {
        return $debugBar !== null && method_exists($debugBar, 'addQuery');
    }

    /**
     * @param array<int, mixed> $bindings
     */
    public static function addQuery(
        ?object $debugBar,
        string $sql,
        array $bindings,
        float $timeMs,
        string $connection
    ): void {
        if (!self::supports($debugBar)) {
            return;
        }

        $debugBar->addQuery($sql, $bindings, $timeMs, $connection);
    }

    public static function render(?object $debugBar): string
    {
        if (!self::supports($debugBar)) {
            return '';
        }

        $rendererClass = 'Marwa\\DebugBar\\Renderer';

        if (!class_exists($rendererClass)) {
            return '';
        }

        return (new $rendererClass($debugBar))->render();
    }
}
