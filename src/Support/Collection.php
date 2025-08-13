<?php

declare(strict_types=1);

namespace Marwa\DB\Support;

final class Collection implements \IteratorAggregate, \Countable
{
    /**
     * @var array<int|string, mixed>
     */
    private array $items;

    /**
     * @param array<int|string, mixed> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /** @return array<int|string, mixed> */
    public function toArray(): array
    {
        return $this->items;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }

    // ---- Convenience ----

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function first(): mixed
    {
        return $this->items[array_key_first($this->items)] ?? null;
    }

    public function map(callable $callback): self
    {
        return new self(\array_map($callback, $this->items));
    }

    public function filter(?callable $callback = null): self
    {
        return new self(
            $callback ? \array_values(\array_filter($this->items, $callback))
                : \array_values(\array_filter($this->items))
        );
    }

    /**
     * Reduce items to a single value.
     *
     * @template T
     * @param callable(T,mixed):T $callback
     * @param T $initial
     * @return T
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return \array_reduce($this->items, $callback, $initial);
    }

    // ---- Numeric helpers ----
    // All support: null (entire item), string key, or callable transformer.

    /**
     * @param callable|string|null $by
     */
    public function max(callable|string|null $by = null): mixed
    {
        $vals = $this->extractValues($by);
        return $vals ? \max($vals) : null;
    }

    /**
     * @param callable|string|null $by
     */
    public function min(callable|string|null $by = null): mixed
    {
        $vals = $this->extractValues($by);
        return $vals ? \min($vals) : null;
    }

    /**
     * Sum numeric values.
     * @param callable|string|null $by
     */
    public function sum(callable|string|null $by = null): int|float
    {
        $vals = $this->extractValues($by, true);
        return \array_sum($vals);
    }

    /**
     * Average (mean) of numeric values.
     * @param callable|string|null $by
     */
    public function avg(callable|string|null $by = null): float|null
    {
        $vals = $this->extractValues($by, true);
        $n = \count($vals);
        return $n > 0 ? (\array_sum($vals) / $n) : null;
    }

    // ---- Internals ----

    /**
     * @return array<int, mixed>
     */
    private function extractValues(callable|string|null $by, bool $numericOnly = false): array
    {
        $vals = [];

        foreach ($this->items as $item) {
            $value = null;

            if ($by === null) {
                $value = $item;
            } elseif (\is_string($by)) {
                if (\is_array($item) && \array_key_exists($by, $item)) {
                    $value = $item[$by];
                } elseif (\is_object($item) && isset($item->{$by})) {
                    $value = $item->{$by};
                }
            } elseif (\is_callable($by)) {
                $value = $by($item);
            }

            if ($value === null) {
                continue;
            }

            if ($numericOnly) {
                if (\is_numeric($value)) {
                    $vals[] = $value + 0; // cast numeric string to int/float
                }
            } else {
                $vals[] = $value;
            }
        }

        return $vals;
    }
}
