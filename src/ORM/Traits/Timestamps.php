<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

trait Timestamps
{
    protected static bool $timestamps = true;

    public function usesTimestamps(): bool
    {
        return static::$timestamps;
    }

    public function createdAt(): ?string
    {
        return $this->attributes['created_at'] ?? null;
    }

    public function updatedAt(): ?string
    {
        return $this->attributes['updated_at'] ?? null;
    }

    public function touch(?string $attribute = null): bool
    {
        $data = [];

        if ($attribute !== null) {
            $data[$attribute] = date('Y-m-d H:i:s');
        } else {
            $this->touchTimestamps($data);
        }

        $this->attributes = array_replace($this->attributes, $data);

        if ($this->exists) {
            return static::baseQuery()
                ->where(static::$primaryKey, '=', $this->getKey())
                ->update($data) > 0;
        }

        return false;
    }

    /** @param array<mixed> $data */
    protected function touchTimestamps(array &$data): void
    {
        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if (!isset($this->attributes['created_at']) && !isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
    }
}
