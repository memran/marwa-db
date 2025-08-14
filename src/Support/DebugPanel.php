<?php

declare(strict_types=1);

namespace Marwa\DB\Support;

final class DebugPanel
{
    /**
     * @var array<int, array{sql: string, bindings: array, time: string}>
     */
    private array $queries = [];

    public function addQuery(string $sql, array $bindings, float $timeMs): void
    {
        $this->queries[] = [
            'sql'      => $sql,
            'bindings' => $bindings,
            'time'     => number_format($timeMs, 2) . ' ms',
        ];
    }

    public function render(): string
    {
        if (empty($this->queries)) {
            return '<div><strong>Debug Panel:</strong> No queries executed.</div>';
        }

        ob_start();
        echo '<div><strong>Debug Panel — ' . count($this->queries) . ' queries</strong></div>';
        echo '<table border="1" cellpadding="5" cellspacing="0" style="font-size:12px;font-family:monospace;">';
        echo '<thead><tr><th>#</th><th>SQL</th><th>Bindings</th><th>Time</th></tr></thead><tbody>';

        foreach ($this->queries as $i => $q) {
            echo '<tr>';
            echo '<td>' . ($i + 1) . '</td>';
            echo '<td>' . htmlspecialchars($q['sql']) . '</td>';
            echo '<td>' . htmlspecialchars(json_encode($q['bindings'], JSON_UNESCAPED_UNICODE)) . '</td>';
            echo '<td>' . $q['time'] . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        return ob_get_clean();
    }
}
