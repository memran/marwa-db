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
        if (isset($this->connections[$name]) && is_array($this->connections[$name])) {
            return $this->connections[$name];
        }

        if ($name === 'default' && isset($this->connections['default']) && is_string($this->connections['default'])) {
            $resolved = $this->connections['default'];
            return $this->connections['connections'][$resolved] ?? [];
        }

        if (isset($this->connections['connections'][$name]) && is_array($this->connections['connections'][$name])) {
            return $this->connections['connections'][$name];
        }

        return [];
    }

    public function has(string $name): bool
    {
        return $this->get($name) !== [];
    }
}
