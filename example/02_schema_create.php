<?php
require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Schema\Builder;

$config = new Config([
    'default' => 'sqlite',
    'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']],
]);

$cm = new ConnectionManager($config);
$schema = new Builder($cm, 'sqlite');

$schema->create('posts', function ($table) {
    $table->id();
    $table->string('title');
    $table->text('body');
    $table->timestamps();
});

echo "Table 'posts' created!\n";
