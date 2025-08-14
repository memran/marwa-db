<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

interface Grammar
{
    /** @return string[] SQL statements */
    public function compileCreate(Blueprint $blueprint): array;

    /** @return string[] SQL statements for altering table */
    public function compileTable(Blueprint $blueprint): array;

    /** @return string[] */
    public function compileDrop(string $table): array;

    /** @return string[] */
    public function compileRename(string $from, string $to): array;
}
