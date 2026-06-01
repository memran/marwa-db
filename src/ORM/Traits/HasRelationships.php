<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

use Marwa\DB\ORM\Relations\Relation;

trait HasRelationships
{
    /**
     * Eager-loaded relationship results store.
     * @var array<string, mixed>
     */
    protected array $relations = [];

    public function setRelation(string $name, mixed $value): static
    {
        $this->relations[$name] = $value;
        return $this;
    }

    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    public function relationLoaded(string $name): bool
    {
        return array_key_exists($name, $this->relations);
    }

    /**
     * Lazy-resolve a relation by name, caching the result.
     */
    public function getRelationValue(string $name): mixed
    {
        if ($this->relationLoaded($name)) {
            return $this->getRelation($name);
        }

        if (!method_exists($this, $name)) {
            return null;
        }

        $rel = $this->{$name}();

        if ($rel instanceof Relation) {
            $value = $rel->getResults($this);
            $this->setRelation($name, $value);
            return $value;
        }

        return $rel;
    }
}
