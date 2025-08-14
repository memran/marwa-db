<?php

declare(strict_types=1);

namespace Database\Seeders;

use Marwa\DB\Seeder\Seeder;
use Faker\Generator;
use Marwa\DB\ORM\Model;
use App\Models\User;

final class UsersTableSeeder implements Seeder
{
    public function run(Generator $faker): void
    {
        // Ensure Model has ConnectionManager set in your bootstrap
        for ($i = 0; $i < 25; $i++) {
            User::create([
                'name'  => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
            ]);
        }
    }
}
