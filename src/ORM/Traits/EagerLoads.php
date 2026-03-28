<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

trait EagerLoads
{
    protected array $eager = [];

    public function load(string ...$relations): static
    {
        foreach ($relations as $relation) {
            if (method_exists($this, $relation)) {
                $this->relations[$relation] = $this->{$relation}();
            }
        }

        return $this;
    }

    public static function with(string ...$relations): static
    {
        $instance = new static();
        $instance->eager = $relations;

        return $instance;
    }
}
