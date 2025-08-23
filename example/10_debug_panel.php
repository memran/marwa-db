<?php
require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Debug\DebugPanel;

$config = new Config([
    'default' => 'sqlite',
    'globals' => ['debug' => true],
    'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']],
]);

$cm = new ConnectionManager($config);
$panel = new DebugPanel(1); // threshold 1ms
$cm->setDebugPanel($panel);

$pdo = $cm->getPdo();
$pdo->exec("CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)");
$pdo->exec("INSERT INTO test (name) VALUES ('Debug')");

// Render debug panel (only shows in non-CLI environments)
echo $panel->render();
