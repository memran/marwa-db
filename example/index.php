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

$examples = [
    'Getting started' => [
        '01_basic_connection.php' => 'Basic connection',
        '02_schema_create.php' => 'Schema creation',
        '03_migration_run.php' => 'Run migrations',
        '08_seeder_run.php' => 'Seeder execution',
    ],
    'Core ORM' => [
        '04_model_crud.php' => 'Model CRUD',
        '05_relationships.php' => 'Relationships',
        '06_soft_deletes.php' => 'Soft deletes',
        '07_mass_assignment.php' => 'Mass assignment',
        '10_debug_panel.php' => 'Debug panel',
    ],
    'ORM examples' => [
        '11_relations_eager_loading.php' => 'Relations and eager loading',
        '13_transactions_updates.php' => 'Transactions and updates',
        '14_with_count.php' => 'withCount examples',
        '15_soft_deletes_restore.php' => 'Soft delete restore',
        '16_many_to_many_pivot.php' => 'Many-to-many pivot data',
        '18_chunk_by_id_scopes.php' => 'Chunk by ID and scopes',
    ],
    'Query builder' => [
        '12_pagination_chunks.php' => 'Pagination and chunks',
        '17_json_queries.php' => 'JSON query conditions',
        '09_transactions.php' => 'Transactions',
    ],
];

echo '<pre>';
print_r($rows);
echo '</pre>';
if ($db['default']['debug']) {
    echo $panel->render();
}

echo '<h2>Examples</h2>';
foreach ($examples as $group => $files) {
    echo '<h3>' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . '</h3>';
    echo '<ul>';
    foreach ($files as $file => $label) {
        echo '<li><a href="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
    echo '</ul>';
}
