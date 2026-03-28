<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Seeder;

use Marwa\DB\Seeder\AbstractSeeder;
use PHPUnit\Framework\TestCase;

final class AbstractSeederTest extends TestCase
{
    public function testConcreteSeederCanExtendAbstractSeeder(): void
    {
        $seeder = new class extends AbstractSeeder {
            public bool $ran = false;

            public function run(): void
            {
                $this->ran = true;
            }
        };

        $seeder->run();

        self::assertTrue($seeder->ran);
    }
}
