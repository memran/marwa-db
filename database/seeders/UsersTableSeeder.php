<?php

declare(strict_types=1);

namespace Marwa\DB\Seeder;

use Marwa\DB\Seeder\Seeder;
use App\Models\User;
use Faker\Factory as FakerFactory;

final class UsersTableSeeder implements Seeder
{
    public function run(): void
    {

        //User::destroy([10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20]);
        // Add your own fake data strategy; or use Faker inside here if you want.
        // $faker = FakerFactory::create();

        // for ($i = 0; $i < 10; $i++) {
        //     User::create([
        //         'name'  => $faker->name()
        //     ]);
        // }
    }
}
