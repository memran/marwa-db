<?php

$basePath = dirname(__FILE__, 2);
define('ROOT_PATH', $basePath);

require  ROOT_PATH . '/vendor/autoload.php';

use Marwa\DB\Bootstrap;
use Marwa\DB\Facades\DB;

$db = require ROOT_PATH . '/config/database.php';
$manager = Bootstrap::init($db, null, true); // enable debug panel in web context


DB::setManager($manager);
$rows = DB::table('user')
    ->where('name', 'like', 'J%')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();  // arrays/objects depending on fetch mode

var_dump($rows);
