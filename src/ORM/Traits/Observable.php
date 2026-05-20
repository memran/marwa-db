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

    public static function creating(callable $callback): void
    {
        static::observe('creating', $callback);
    }

    public static function created(callable $callback): void
    {
        static::observe('created', $callback);
    }

    public static function updating(callable $callback): void
    {
        static::observe('updating', $callback);
    }

    public static function updated(callable $callback): void
    {
        static::observe('updated', $callback);
    }

    public static function saving(callable $callback): void
    {
        static::observe('saving', $callback);
    }

    public static function saved(callable $callback): void
    {
        static::observe('saved', $callback);
    }

    public static function deleting(callable $callback): void
    {
        static::observe('deleting', $callback);
    }

    public static function deleted(callable $callback): void
    {
        static::observe('deleted', $callback);
    }

    public static function restoring(callable $callback): void
    {
        static::observe('restoring', $callback);
    }

    public static function restored(callable $callback): void
    {
        static::observe('restored', $callback);
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

    public static function onRestoring(callable $callback): void
    {
        static::observe('restoring', $callback);
    }

    public static function onRestored(callable $callback): void
    {
        static::observe('restored', $callback);
    }

    protected static function fireEvent(string $event, Model $model): void
    {
        $callbacks = static::$observers[$event] ?? [];
        foreach ($callbacks as $callback) {
            $callback($model);
        }
    }
}
