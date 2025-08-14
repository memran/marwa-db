<?php

namespace Marwa\DB\Seeder;

use Marwa\DB\Facades\DB;

abstract class AbstractSeeder implements Seeder
{

    /**
     * @param string $sqlString
     * @param array $params
     * @return mixed
     */
    public function execute(string $sqlString, array $params = [])
    {
        //return DB::query()->rawQuery($sqlString);
    }

    /**
     *
     */
    abstract public function run(): void;
}
