<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Support;

use Marwa\DB\Support\DebugBarAdapter;
use PHPUnit\Framework\TestCase;

final class DebugBarAdapterTest extends TestCase
{
    public function testCreateDefaultReturnsInstalledDebugBar(): void
    {
        $debugBar = DebugBarAdapter::createDefault();

        self::assertIsObject($debugBar);
        self::assertTrue(DebugBarAdapter::supports($debugBar));
    }

    public function testRenderReturnsHtmlForInstalledDebugBar(): void
    {
        $debugBar = DebugBarAdapter::createDefault();
        DebugBarAdapter::addQuery($debugBar, 'SELECT 1', [], 1.25, 'default');

        $html = DebugBarAdapter::render($debugBar);

        self::assertStringContainsString('Queries', $html);
        self::assertStringContainsString('SELECT 1', $html);
    }
}
