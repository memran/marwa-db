<?php

declare(strict_types=1);

namespace Marwa\DB\Query;

/**
 * Tiny raw expression helper. Wrap any snippet that should NOT be quoted/wrapped.
 */
class Expression
{
    public function __construct(private string $value) {}
    public function __toString(): string
    {
        return $this->value;
    }
}
