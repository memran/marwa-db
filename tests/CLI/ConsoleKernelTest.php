<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\CLI;

use PHPUnit\Framework\TestCase;

final class ConsoleKernelTest extends TestCase
{
    public function testCliListCommandRunsSuccessfully(): void
    {
        $root = dirname(__DIR__, 2);
        $cmd = sprintf(
            'cd %s && php bin/marwa-db list 2>&1',
            escapeshellarg($root)
        );

        exec($cmd, $output, $exitCode);

        self::assertSame(0, $exitCode, implode(PHP_EOL, $output));
        self::assertStringContainsString('Marwa-DB CLI', implode(PHP_EOL, $output));
        self::assertStringContainsString('db:seed', implode(PHP_EOL, $output));
    }
}
