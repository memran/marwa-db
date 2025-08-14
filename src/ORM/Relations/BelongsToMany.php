<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Relations;

use Marwa\DB\ORM\Model;

/**
 * BelongsToMany: Many-to-Many via a pivot table.
 *
 * Example wiring in a model:
 *   return new BelongsToMany(
 *     static::$cm, static::$connection,
 *     static::class,               // parent model class (e.g., User::class)
 *     Role::class,                 // related model class
 *     'role_user',                 // pivot table
 *     'user_id',                   // pivot col that references parent key
 *     'role_id',                   // pivot col that references related key
 *     'id',                        // parent key name on parent table
 *     'id',                        // related key name on related table
 *     ['granted_at']               // (optional) extra pivot columns to include
 *   );
 */
final class BelongsToMany extends Relation
{
    /** @var string[] */
    private array $pivotColumns;

    public function __construct(
        $cm,
        string $connection,
        string $parentClass,
        string $related,
        private string $pivotTable,
        private string $foreignPivotKey,   // references parent key on pivot (e.g., user_id)
        private string $relatedPivotKey,   // references related key on pivot (e.g., role_id)
        private string $parentKey    = 'id',
        private string $relatedKey   = 'id',
        array $pivotColumns = []          // additional pivot columns to include
    ) {
        parent::__construct($cm, $connection, $parentClass, $related);
        $this->pivotColumns = $pivotColumns;
    }

    /**
     * Batch eager load:
     * 1) Get all parent IDs
     * 2) Load pivot rows for those parents
     * 3) Load related rows for unique related IDs
     * 4) Attach related models to each parent (with 'pivot' relation containing pivot data)
     */
    public function eagerLoad(array $models, string $name): void
    {
        if (!$models) return;

        // 1) collect parent IDs
        $parentIds = [];
        foreach ($models as $m) {
            if ($m instanceof Model) {
                $id = $m->getAttribute($this->parentKey);
                if ($id !== null) $parentIds[] = $id;
            }
        }
        $parentIds = array_values(array_unique($parentIds));
        if (!$parentIds) return;

        // 2) load pivots for those parents
        $pivotQb = $this->qb($this->pivotTable)->whereIn($this->foreignPivotKey, $parentIds);
        $pivotRows = $pivotQb->get();

        // Build: parentId => [relatedId => pivotDataArray]
        $pivotMap = [];
        $relatedIds = [];
        foreach ($pivotRows as $row) {
            $arr = is_array($row) ? $row : (array)$row;
            $pId = $arr[$this->foreignPivotKey] ?? null;
            $rId = $arr[$this->relatedPivotKey] ?? null;
            if ($pId === null || $rId === null) continue;

            $relatedIds[] = $rId;

            // keep only desired pivot columns (always store foreign/related keys)
            $pivotData = [
                $this->foreignPivotKey => $pId,
                $this->relatedPivotKey => $rId,
            ];
            foreach ($this->pivotColumns as $col) {
                if (array_key_exists($col, $arr)) {
                    $pivotData[$col] = $arr[$col];
                }
            }

            $pivotMap[$pId][$rId] = $pivotData;
        }

        $relatedIds = array_values(array_unique($relatedIds));
        if (!$relatedIds) {
            // Assign empty arrays for this relation
            foreach ($models as $m) {
                if ($m instanceof Model) $m->setRelation($name, []);
            }
            return;
        }

        // 3) load related rows
        /** @var class-string<Model> $relCls */
        $relCls = $this->related;
        $relatedRows = $this->qb($relCls::$table)->whereIn($this->relatedKey, $relatedIds)->get();

        $relatedModelsById = [];
        foreach ($relatedRows as $row) {
            $arr = is_array($row) ? $row : (array)$row;
            $rid = $arr[$this->relatedKey] ?? null;
            if ($rid === null) continue;
            $relatedModelsById[$rid] = new $relCls($arr, true);
        }

        // 4) attach to each parent (hydrate + attach pivot as a 'pivot' relation on each related)
        foreach ($models as $parent) {
            if (!$parent instanceof Model) continue;
            $pid = $parent->getAttribute($this->parentKey);
            $bucket = [];

            if ($pid !== null && isset($pivotMap[$pid])) {
                foreach ($pivotMap[$pid] as $rid => $pivot) {
                    if (isset($relatedModelsById[$rid])) {
                        $relModel = clone $relatedModelsById[$rid]; // avoid sharing same instance across parents
                        // attach pivot information onto the related model
                        $relModel->setRelation('pivot', $pivot);
                        $bucket[] = $relModel;
                    }
                }
            }

            $parent->setRelation($name, $bucket);
        }
    }

    /** ---------- Optional write helpers on the pivot ---------- */

    /**
     * Attach one or many related IDs to a given parent row (optionally with extra pivot data).
     * $ids can be: [1,2,3] or [1 => ['granted_at' => '...'], 2 => ['...']]
     */
    public function attach(Model $parent, array|int|string $ids, array $pivotData = []): int
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $inserted = 0;

        foreach ($ids as $idKey => $val) {
            // if associative: id => pivotData
            if (is_int($idKey) || is_string($idKey)) {
                $relatedId = is_array($val) ? $idKey : $val;
                $extra     = is_array($val) ? $val : $pivotData;
            } else {
                $relatedId = $val;
                $extra     = $pivotData;
            }

            $data = array_merge($extra, [
                $this->foreignPivotKey => $parent->getAttribute($this->parentKey),
                $this->relatedPivotKey => $relatedId,
            ]);

            $inserted += $this->qb($this->pivotTable)->insert($data);
        }

        return $inserted;
    }

    /**
     * Detach one/many related IDs from a given parent. If $ids is null, detach all.
     */
    public function detach(Model $parent, array|int|string|null $ids = null): int
    {
        $qb = $this->qb($this->pivotTable)
            ->where($this->foreignPivotKey, '=', $parent->getAttribute($this->parentKey));

        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $qb->whereIn($this->relatedPivotKey, $ids);
        }

        return $qb->delete();
    }

    /**
     * Sync pivot records to exactly match given IDs (optionally id => pivotData).
     * Returns ['attached' => [], 'detached' => [], 'updated' => []]
     */
    public function sync(Model $parent, array $ids): array
    {
        // Fetch current related ids
        $current = $this->qb($this->pivotTable)
            ->select($this->relatedPivotKey)
            ->where($this->foreignPivotKey, '=', $parent->getAttribute($this->parentKey))
            ->get();

        $currentIds = [];
        foreach ($current as $row) {
            $arr = is_array($row) ? $row : (array)$row;
            $rid = $arr[$this->relatedPivotKey] ?? null;
            if ($rid !== null) $currentIds[] = $rid;
        }

        $incomingIds = [];
        $incomingPivot = [];
        foreach ($ids as $k => $v) {
            if (is_int($k) || is_string($k)) {
                if (is_array($v)) { // id => pivotData
                    $incomingIds[] = $k;
                    $incomingPivot[$k] = $v;
                } else {
                    $incomingIds[] = $v;
                }
            } else {
                $incomingIds[] = $v;
            }
        }

        $attach = array_values(array_diff($incomingIds, $currentIds));
        $detach = array_values(array_diff($currentIds, $incomingIds));
        $update = array_values(array_intersect($incomingIds, $currentIds));

        // Perform detach
        if ($detach) {
            $this->qb($this->pivotTable)
                ->where($this->foreignPivotKey, '=', $parent->getAttribute($this->parentKey))
                ->whereIn($this->relatedPivotKey, $detach)
                ->delete();
        }

        // Perform attach
        foreach ($attach as $rid) {
            $extra = $incomingPivot[$rid] ?? [];
            $this->attach($parent, [$rid => $extra]);
        }

        // Perform updates (only extra pivot data)
        foreach ($update as $rid) {
            if (!isset($incomingPivot[$rid])) continue;
            $this->qb($this->pivotTable)
                ->where($this->foreignPivotKey, '=', $parent->getAttribute($this->parentKey))
                ->where($this->relatedPivotKey, '=', $rid)
                ->update($incomingPivot[$rid]);
        }

        return ['attached' => $attach, 'detached' => $detach, 'updated' => $update];
    }
}
