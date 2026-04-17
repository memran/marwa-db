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
                $buckets[$fk] = new $rel($arr, true);
            }
        }

        foreach ($models as $m) {
            if (!$m instanceof Model) continue;
            $key = $m->getAttribute($this->localKey);
            $m->setRelation($name, $buckets[$key] ?? null);
        }
    }
}