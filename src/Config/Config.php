<?php

declare(strict_types=1);

namespace Marwa\DB\Config;

final class Config
{
    public function __construct(private array $connections) {}

    public function all(): array
    {
        return $this->connections;
    }

    public function get(string $name = 'default'): array
    {
        return $this->connections[$name] ?? [];
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->connections);
    }
}
