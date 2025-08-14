<?php

declare(strict_types=1);

namespace Marwa\DB\Seeder;

use Faker\Generator;

interface Seeder
{
    public function run(Generator $faker): void;
}
