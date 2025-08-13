<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

trait EagerLoads
{
    protected array $eager = [];

    public function load(string ...$relations): static
    {
        foreach ($relations as $rel) {
            if (method_exists($this, $rel)) {
                $this->relations[$rel] = $this->{$rel}();
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
