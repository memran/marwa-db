<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

use Marwa\Support\Json;

trait HasAttributes
{
    /** @var array<string> */
    protected array $hidden = [];

    /** @var array<string> */
    protected array $visible = [];

    /** @var array<string> */
    protected array $appends = [];

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function fill(array $attributes): static
    {
        /** @var array<string,mixed> $filtered */
        $filtered = static::filterFillable($attributes);
        $this->attributes = array_replace($this->attributes, $filtered);

        return $this;
    }

    public function forceFill(array $attributes): static
    {
        $this->attributes = array_replace($this->attributes, $attributes);

        return $this;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->attributes, array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->attributes, array_flip($keys));
    }

    public function append(array $attributes): static
    {
        $this->appends = array_merge($this->appends, $attributes);

        return $this;
    }

    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function setVisible(array $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /** @return array<string,mixed> */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $k => $v) {
            $orig = $this->original[$k] ?? null;
            if ($v !== $orig) $dirty[$k] = $v;
        }
        return $dirty;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $arr = [];

        foreach ($this->attributes as $k => $v) {
            if ($this->isHidden($k)) {
                continue;
            }
            if ($this->visible !== [] && !in_array($k, $this->visible, true)) {
                continue;
            }
            $arr[$k] = $this->castOut($k, $v);
        }

        foreach ($this->appends as $key) {
            $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key))) . 'Attribute';
            if (method_exists($this, $getter)) {
                $arr[$key] = $this->{$getter}();
            }
        }

        foreach ($this->relations as $k => $rel) {
            if ($this->isHidden($k)) {
                continue;
            }
            if ($this->visible !== [] && !in_array($k, $this->visible, true)) {
                continue;
            }
            $arr[$k] = is_array($rel)
                ? array_map(fn($m) => $m instanceof self ? $m->toArray() : $m, $rel)
                : ($rel instanceof self ? $rel->toArray() : $rel);
        }

        return $arr;
    }

    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return Json::encode($this->toArray(), $options);
    }

    protected function isHidden(string $key): bool
    {
        return in_array($key, $this->hidden, true);
    }
}
