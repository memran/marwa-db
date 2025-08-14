<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Relations;

use Marwa\DB\ORM\Model;

final class BelongsTo extends Relation
{
    public function __construct(
        $cm,
        string $connection,
        string $parentClass,
        string $related,
        private string $foreignKey, // on parent
        private string $ownerKey = 'id'
    ) {
        parent::__construct($cm, $connection, $parentClass, $related);
    }

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
        $rows = $this->qb($rel::$table)->whereIn($this->ownerKey, $ownerIds)->get();

        $owners = [];
        foreach ($rows as $row) {
            $arr = is_array($row) ? $row : (array)$row;
            $ok  = $arr[$this->ownerKey] ?? null;
            if ($ok === null) continue;
            $owners[$ok] = new $rel($arr, true);
        }

        foreach ($models as $m) {
            if (!$m instanceof Model) continue;
            $fk = $m->getAttribute($this->foreignKey);
            $m->setRelation($name, $fk !== null && isset($owners[$fk]) ? $owners[$fk] : null);
        }
    }
}
