<?php

declare(strict_types=1);

namespace Marwa\DB\Config;

/**
 * @phpstan-type DbConnectionConfig array{driver:string,host?:string,port?:int,database:string,username?:string,password?:string,charset?:string,options?:array<int,int>,debug?:bool}
 * @phpstan-type DbConfigShape array<string,DbConnectionConfig|array{default?:string,connections?:array<string,DbConnectionConfig>}>
 */
final class Config
{
    /**
     * @param DbConfigShape $connections
     */
    public function __construct(private array $connections) {}

    /** @return DbConfigShape */
    public function all(): array
    {
        return $this->connections;
    }

    /** @return DbConnectionConfig */
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
