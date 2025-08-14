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

    /** Batch-load relation for many parent models in one query and set on each model */
    abstract public function eagerLoad(array $models, string $name): void;

    protected function qb(string $table): BaseBuilder
    {
        return (new BaseBuilder($this->cm, $this->connection))->table($table);
    }
}
