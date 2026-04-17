<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

use Marwa\DB\ORM\Model;

trait Observable
{
    protected static array $observers = [];

    public static function observe(string $event, callable $callback): void
    {
        static::$observers[$event][] = \Closure::bind($callback, null, static::class);
    }

    public static function setObservers(array $observers): void
    {
        foreach ($observers as $event => $callback) {
            static::$observers[$event][] = \Closure::bind($callback, null, static::class);
        }
    }

    public static function onCreating(callable $callback): void
    {
        static::observe('creating', $callback);
    }

    public static function onCreated(callable $callback): void
    {
        static::observe('created', $callback);
    }

    public static function onUpdating(callable $callback): void
    {
        static::observe('updating', $callback);
    }

    public static function onUpdated(callable $callback): void
    {
        static::observe('updated', $callback);
    }

    public static function onSaving(callable $callback): void
    {
        static::observe('saving', $callback);
    }

    public static function onSaved(callable $callback): void
    {
        static::observe('saved', $callback);
    }

    public static function onDeleting(callable $callback): void
    {
        static::observe('deleting', $callback);
    }

    public static function onDeleted(callable $callback): void
    {
        static::observe('deleted', $callback);
    }

    protected static function fireEvent(string $event, Model $model): void
    {
        $callbacks = static::$observers[$event] ?? [];
        foreach ($callbacks as $callback) {
            $callback($model);
        }
    }
}