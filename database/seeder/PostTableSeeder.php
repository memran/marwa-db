<?php

declare(strict_types=1);

namespace Database\Seeders;

use Marwa\DB\Seeder\Seeder;
use Faker\Generator;
use App\Models\Post;

final class PostsTableSeeder implements Seeder
{
    public function run(Generator $faker): void
    {
        for ($i = 0; $i < 50; $i++) {
            Post::create([
                'user_id' => $faker->numberBetween(1, 25),
                'title'   => $faker->sentence(6),
                'body'    => $faker->paragraph(3),
            ]);
        }
    }
}
