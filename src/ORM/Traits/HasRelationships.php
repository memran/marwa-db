<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

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
}
