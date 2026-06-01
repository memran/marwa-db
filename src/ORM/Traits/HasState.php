<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

trait HasState
{
    public function isDirty(?string $attribute = null): bool
    {
        $dirty = $this->getDirty();

        if ($attribute !== null) {
            return array_key_exists($attribute, $dirty);
        }

        return $dirty !== [];
    }

    public function isClean(?string $attribute = null): bool
    {
        return !$this->isDirty($attribute);
    }

    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    public function refresh(): static
    {
        $key = $this->getKey();
        if ($key === null) return $this;

        $fresh = static::query()->where(static::$primaryKey, '=', $key)->first();

        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->original   = $fresh->attributes;
            $this->exists     = true;
        }
        return $this;
    }

    public function fresh(): ?static
    {
        $key = $this->getKey();

        if ($key === null) {
            return null;
        }

        return static::find($key);
    }

    public function replicate(?array $except = null): static
    {
        $attributes = $this->attributes;

        $except = $except ?? [static::$primaryKey];

        foreach ($except as $key) {
            unset($attributes[$key]);
        }

        return new static($attributes);
    }
}
