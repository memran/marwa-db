<?php

declare(strict_types=1);

namespace Marwa\DB\Query;

final class Grammar
{
    public function wrap(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function parameterize(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
