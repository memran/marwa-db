<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Relations;

use Marwa\DB\ORM\Model;
use Marwa\DB\Connection\ConnectionManager;

final class HasOne extends Relation
{
    public function __construct(
        ConnectionManager $cm,
        string $connection,
        string $parentClass,
        string $related,
        private string $foreignKey,
        private string $localKey = 'id'
    ) {
        parent::__construct($cm, $connection, $parentClass, $related);
    }

    /** @param array<Model> $models */
    public function eagerLoad(array $models, string $name): void
    {
        if (!$models) return;

        $parentKeys = [];
        foreach ($models as $m) {
            if ($m instanceof Model) {
                $k = $m->getAttribute($this->localKey);
                if ($k !== null) $parentKeys[] = $k;
            }
        }
        $parentKeys = array_values(array_unique($parentKeys));
        if (!$parentKeys) return;

        /** @var class-string<Model> $rel */
        $rel = $this->related;
        $rows = $this->qb($rel::table())->whereIn($this->foreignKey, $parentKeys)->get();

        $buckets = [];
        foreach ($rows as $row) {
            $arr = is_array($row) ? $row : (array)$row;
            $fk = $arr[$this->foreignKey] ?? null;
            if ($fk === null) continue;
            if (!isset($buckets[$fk])) {
                $buckets[$fk] = $rel::hydrateRow($arr);
            }
        }

        foreach ($models as $m) {
            if (!$m instanceof Model) continue;
            $key = $m->getAttribute($this->localKey);
            $m->setRelation($name, $buckets[$key] ?? null);
        }
    }

    public function first(Model $parent): ?Model
    {
        $lk = $parent->getAttribute($this->localKey);
        if ($lk === null) return null;
        $rel = $this->related;
        $row = $this->qb($rel::table())
            ->where($this->foreignKey, '=', $lk)
            ->first();
        if ($row === null) return null;
        return $rel::hydrateRow((array)$row);
    }

    public function getResults(Model $parent): ?Model
    {
        return $this->first($parent);
    }

    /** @param array<Model> $models */
    public function eagerCount(array $models, string ...$aliases): void
    {
        if (!$models) {
            return;
        }

        $parentKeys = [];
        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }

            $key = $model->getAttribute($this->localKey);
            if ($key !== null) {
                $parentKeys[] = $key;
            }
        }

        $parentKeys = array_values(array_unique($parentKeys));
        if (!$parentKeys) {
            foreach ($models as $model) {
                if ($model instanceof Model) {
                    foreach ($aliases as $alias) {
                        $model->setRelation($alias, 0);
                    }
                }
            }

            return;
        }

        /** @var class-string<Model> $rel */
        $rel = $this->related;
        $rows = $this->qb($rel::table())
            ->select($this->foreignKey)
            ->whereIn($this->foreignKey, $parentKeys)
            ->get();

        $exists = [];
        foreach ($rows as $row) {
            $arr = is_array($row) ? $row : (array) $row;
            $fk = $arr[$this->foreignKey] ?? null;
            if ($fk === null) {
                continue;
            }

            $exists[(string) $fk] = 1;
        }

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }

            $key = $model->getAttribute($this->localKey);
            $count = $key === null ? 0 : ($exists[(string) $key] ?? 0);
            foreach ($aliases as $alias) {
                $model->setRelation($alias, $count);
            }
        }
    }

    /** @return list<Model> */
    public function get(Model $parent): array
    {
        $lk = $parent->getAttribute($this->localKey);
        if ($lk === null) return [];
        $rel = $this->related;
        $rows = $this->qb($rel::table())
            ->where($this->foreignKey, '=', $lk)
            ->get();
        $models = [];
        foreach ($rows as $row) {
            $models[] = $rel::hydrateRow((array)$row);
        }
        return $models;
    }
}
