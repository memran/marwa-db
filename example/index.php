<?php

$basePath = dirname(__FILE__, 2);
define('ROOT_PATH', $basePath);

require  ROOT_PATH . '/vendor/autoload.php';

use Marwa\DB\Bootstrap;
use Marwa\DB\Facades\DB;
use Marwa\DB\Support\DebugPanel;

$db = require ROOT_PATH . '/config/database.php';
$manager = Bootstrap::init($db, null, true); // enable debug panel in web context
$panel = new DebugPanel();
$manager->setDebugPanel($panel);

DB::setManager($manager);
$rows = DB::table('users')
    //->where('name', 'like', 'J%')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();  // arrays/objects depending on fetch mode

var_dump($rows);
if ($db['default']['debug']) {
    echo $panel->render();
}
