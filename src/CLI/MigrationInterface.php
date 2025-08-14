<?php

declare(strict_types=1);

namespace Marwa\DB\CLI;


interface MigrationInterface
{

    public function up(): void;

    public function down(): void;
}
