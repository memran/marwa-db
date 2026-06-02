<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DebugBar\Collectors\DbQueryCollector;
use Marwa\DebugBar\Collectors\KpiCollector;
use Marwa\DebugBar\Collectors\LogCollector;
use Marwa\DebugBar\Collectors\MemoryCollector;
use Marwa\DebugBar\Collectors\TimelineCollector;
use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Renderer;

putenv('DEBUGBAR_ENABLED=1');

$bar = new DebugBar(true);
$bar->collectors()->register(KpiCollector::class);
$bar->collectors()->register(TimelineCollector::class);
$bar->collectors()->register(LogCollector::class);
$bar->collectors()->register(DbQueryCollector::class);
$bar->collectors()->register(MemoryCollector::class);

$bar->mark('bootstrap');
$bar->log('info', 'Marwa DebugBar example booted', ['example' => '19_marwa_debugbar.php']);
$bar->addQuery('SELECT * FROM users WHERE id = ?', [1], 12.5, 'sqlite');
$bar->mark('done');

echo '<!doctype html><html><head><meta charset="utf-8"><title>Marwa DebugBar Example</title></head><body>';
echo '<h1>Marwa DebugBar Example</h1>';
echo '<p>This example uses the optional <code>memran/marwa-debugbar</code> package.</p>';
echo (new Renderer($bar))->render();
echo '</body></html>';
