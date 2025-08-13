<?php

declare(strict_types=1);

namespace Marwa\DB\Query;

final class Pagination
{
    /**
     * @template T
     * @param array<T> $rows
     * @return array{data:array<T>, total:int, per_page:int, current_page:int, last_page:int}
     */
    public function make(array $rows, int $total, int $perPage, int $page): array
    {
        return [
            'data' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int)\ceil(max(1, $total) / max(1, $perPage)),
        ];
    }
}
