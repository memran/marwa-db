<?php

declare(strict_types=1);

namespace Marwa\DB\Connection;

final class RetryPolicy
{
    public function __construct(
        private int $attempts = 3,
        private int $delayMs = 300,
        private bool $exponentialBackoff = false
    ) {}

    public function attempts(): int
    {
        return $this->attempts;
    }
    public function delayMs(): int
    {
        return $this->delayMs;
    }
    public function exponentialBackoff(): bool
    {
        return $this->exponentialBackoff;
    }
}
