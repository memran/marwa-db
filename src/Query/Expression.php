<?php

declare(strict_types=1);

namespace Marwa\DB\Query;

final class Expression
{
    public function __construct(public string $value) {}
    public function __toString(): string
    {
        return $this->value;
    }
}
