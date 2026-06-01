<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Relations;

use Marwa\DB\ORM\Model;
use Marwa\DB\Connection\ConnectionManager;

final class BelongsTo extends Relation
{
    public function __construct(
        ConnectionManager $cm,
        string $connection,
        string $parentClass,
        string $related,
        private string $foreignKey, // on parent
        private string $ownerKey = 'id'
    ) {
        parent::__construct($cm, $connection, $parentClass, $related);
    }

    /** @param array<Model> $models */
    public function eagerLoad(array $models, string $name): void
    {
        if (!$models) return;

        $ownerIds = [];
        foreach ($models as $m) {
            if ($m instanceof Model) {
                $fk = $m->getAttribute($this->foreignKey);
                if ($fk !== null) $ownerIds[] = $fk;
            }
        }
        $ownerIds = array_values(array_unique($ownerIds));
        if (!$ownerIds) return;

        /** @var class-string<Model> $rel */
        $rel = $this->related;
        $rows = $this->qb($rel::table())->whereIn($this->ownerKey, $ownerIds)->get();

        $owners = [];
        foreach ($rows as $row) {
            $arr = is_array($row) ? $row : (array)$row;
            $ok  = $arr[$this->ownerKey] ?? null;
            if ($ok === null) continue;
            $owners[$ok] = $rel::hydrateRow($arr);
        }

        foreach ($models as $m) {
            if (!$m instanceof Model) continue;
            $fk = $m->getAttribute($this->foreignKey);
            $m->setRelation($name, $fk !== null && isset($owners[$fk]) ? $owners[$fk] : null);
        }
    }

    public function first(Model $parent): ?Model
    {
        $fk = $parent->getAttribute($this->foreignKey);
        if ($fk === null) return null;
        $rel = $this->related;
        $row = $this->qb($rel::table())
            ->where($this->ownerKey, '=', $fk)
            ->first();
        if ($row === null) return null;
        return $rel::hydrateRow((array)$row);
    }

    public function getResults(Model $parent): ?Model
    {
        return $this->first($parent);
    }

    /** @return list<Model> */
    public function get(Model $parent): array
    {
        $fk = $parent->getAttribute($this->foreignKey);
        if ($fk === null) return [];
        $rel = $this->related;
        $rows = $this->qb($rel::table())
            ->where($this->ownerKey, '=', $fk)
            ->get();
        $models = [];
        foreach ($rows as $row) {
            $models[] = $rel::hydrateRow((array)$row);
        }
        return $models;
    }
}
