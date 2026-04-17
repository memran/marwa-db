<?php

declare(strict_types=1);

namespace Marwa\DB\Support;

use Marwa\Support\Json;
use Marwa\Support\Number;

final class DebugPanel
{
/**
     * @var array<int, array{sql: string, bindings: array<int, mixed>, time: string, connection: string, error: ?string}>
     */
    private array $queries = [];

    /**
     * @param array<int, mixed> $bindings
     */
    public function addQuery(
        string $sql,
        array $bindings,
        float $timeMs,
        string $connection = 'default',
        ?string $error = null
    ): void
    {
        $this->queries[] = [
            'sql'      => $sql,
            'bindings' => $bindings,
            'time'     => Number::format($timeMs, 2) . ' ms',
            'connection' => $connection,
            'error' => $error,
        ];
    }

/**
     * @return array<int, array{sql: string, bindings: array<int, mixed>, time: string, connection: string, error: ?string}>
     */
    public function all(): array
    {
        return $this->queries;
    }

    public function clear(): void
    {
        $this->queries = [];
    }

    public function render(): string
    {
        if (empty($this->queries)) {
            return '<div><strong>Debug Panel:</strong> No queries executed.</div>';
        }

        ob_start();
        echo '<div><strong>Debug Panel - ' . count($this->queries) . ' queries</strong></div>';
        echo '<table border="1" cellpadding="5" cellspacing="0" style="font-size:12px;font-family:monospace;">';
        echo '<thead><tr><th>#</th><th>Connection</th><th>SQL</th><th>Bindings</th><th>Time</th><th>Error</th></tr></thead><tbody>';

        foreach ($this->queries as $i => $q) {
            echo '<tr>';
            echo '<td>' . ($i + 1) . '</td>';
            echo '<td>' . htmlspecialchars($q['connection'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($q['sql'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars(Json::encode($q['bindings'], JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            echo '<td>' . $q['time'] . '</td>';
            echo '<td>' . htmlspecialchars((string) ($q['error'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        return ob_get_clean() ?: '';
    }
}
