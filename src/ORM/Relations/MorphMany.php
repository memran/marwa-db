<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Relations;

use Marwa\DB\ORM\Model;
use Marwa\DB\Connection\ConnectionManager;

final class MorphMany extends Relation
{
    /** @var array<string, class-string<Model>> */
    private array $morphMap = [];

    public function __construct(
        ConnectionManager $cm,
        string $connection,
        string $parentClass,
        string $related,
        private string $morphType = 'morph_type',
        private string $morphId = 'morph_id',
        private string $localKey = 'id'
    ) {
        parent::__construct($cm, $connection, $parentClass, $related);
    }

    /** @param array<string, class-string<Model>> $map */
    public function withMap(array $map): self
    {
        $this->morphMap = $map;
        return $this;
    }

    /** @param array<Model> $models */
    public function eagerLoad(array $models, string $name): void
    {
        if (!$models) return;

        $parentIds = [];
        foreach ($models as $m) {
            $id = $m->getAttribute($this->localKey);
            if ($id !== null) $parentIds[] = $id;
        }
        $parentIds = array_values(array_unique($parentIds));
        if (!$parentIds) return;

        $type = $this->related;
        if (isset($this->morphMap[$type])) {
            $type = $this->morphMap[$type];
        }

        if (!class_exists($type)) return;

        /** @var class-string<Model> $rel */
        $rel = $type;
        $rows = $this->qb($rel::table())
            ->where($this->morphType, '=', $type)
            ->whereIn($this->morphId, $parentIds)
            ->get();

        $buckets = [];
        foreach ($rows as $row) {
            $arr = is_array($row) ? $row : (array)$row;
            $pk = $arr[$this->morphId] ?? null;
            if ($pk === null) continue;
            $buckets[$pk][] = new $rel($arr, true);
        }

        foreach ($models as $m) {
            $key = $m->getAttribute($this->localKey);
            $m->setRelation($name, $buckets[$key] ?? []);
        }
    }
}