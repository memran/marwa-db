<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Relations;

use Marwa\DB\ORM\Model;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Query\Builder as BaseBuilder;

abstract class Relation
{
    /** @param class-string<Model> $related */
    public function __construct(
        protected ConnectionManager $cm,
        protected string $connection,
        protected string $parentClass,
        protected string $related
    ) {}

    /** Batch-load relation for many parent models in one query and set on each model
     * @param array<Model> $models
     */
    abstract public function eagerLoad(array $models, string $name): void;

    /** Lazy-resolve the relation for a single parent model.
     * @return Model|array<Model>|null
     */
    abstract public function getResults(Model $parent): mixed;

    protected function qb(string $table): BaseBuilder
    {
        $builder = (new BaseBuilder($this->cm, $this->connection))->table($table);
        $sds = $this->related::getSoftDeleteState();
        if ($sds['enabled'] && !$sds['includeTrashed'] && !$sds['onlyTrashed']) {
            $builder->whereNull('deleted_at');
        }
        return $builder;
    }
}
