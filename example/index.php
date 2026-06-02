<?php

$basePath = dirname(__FILE__, 2);
define('ROOT_PATH', $basePath);

require  ROOT_PATH . '/vendor/autoload.php';

use Marwa\DB\Bootstrap;
use Marwa\DB\Facades\DB;
use Marwa\DB\Support\DebugPanel;

$db = require ROOT_PATH . '/config/database.php';
$manager = Bootstrap::init($db, null, true);
$panel = new DebugPanel();
$manager->setDebugPanel($panel);

DB::setManager($manager);
$rows = DB::table('users')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

echo '<pre>';
print_r($rows);
echo '</pre>';
if ($db['default']['debug']) {
    echo $panel->render();
}
