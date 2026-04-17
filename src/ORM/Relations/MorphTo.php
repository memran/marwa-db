<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Relations;

use Marwa\DB\ORM\Model;
use Marwa\DB\Connection\ConnectionManager;

final class MorphTo extends Relation
{
    /** @var array<string, class-string<Model>> */
    private array $morphMap = [];

    public function __construct(
        ConnectionManager $cm,
        string $connection,
        string $parentClass,
        string $related,
        private string $morphType = 'morph_type',
        private string $morphId = 'morph_id'
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

        $typeToIds = [];
        foreach ($models as $m) {
            $type = $m->getAttribute($this->morphType);
            $id = $m->getAttribute($this->morphId);
            if ($type === null || $id === null) continue;
            $typeToIds[$type][] = $id;
        }

        if (!$typeToIds) return;

        foreach ($typeToIds as $type => $ids) {
            $relatedClass = $this->morphMap[$type] ?? $type;
            if (!class_exists($relatedClass)) continue;

            /** @var class-string<Model> $rel */
            $rel = $relatedClass;
            $rows = $rel::query()->whereIn('id', array_unique($ids))->get();

            $idToModel = [];
            foreach ($rows as $row) {
                $arr = is_array($row) ? $row : (array)$row;
                $idToModel[(string)($arr['id'] ?? '')] = new $rel($arr, true);
            }

            foreach ($models as $m) {
                if ($m->getAttribute($this->morphType) !== $type) continue;
                $mid = $m->getAttribute($this->morphId);
                $m->setRelation($name, $idToModel[$mid] ?? null);
            }
        }
    }
}