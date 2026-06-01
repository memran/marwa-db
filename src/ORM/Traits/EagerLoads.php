<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

use Marwa\DB\ORM\Relations\Relation;

trait EagerLoads
{
    /** @var array<string> */
    protected array $eager = [];

    public function load(string ...$relations): static
    {
        foreach ($relations as $relation) {
            if (!method_exists($this, $relation)) continue;
            $rel = $this->{$relation}();
            if ($rel instanceof Relation) {
                $rel->eagerLoad([$this], $relation);
            } elseif (!isset($this->relations[$relation])) {
                $this->relations[$relation] = $rel;
            }
        }

        return $this;
    }

    public function loadMissing(string ...$relations): static
    {
        foreach ($relations as $relation) {
            if (!$this->relationLoaded($relation)) {
                $this->load($relation);
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
