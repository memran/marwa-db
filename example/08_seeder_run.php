
<?php
require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Seeders\SeedRunner;
use Marwa\DB\Seeders\Seeder;

$config = new Config([
    'default' => 'sqlite',
    'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']],
]);

$cm = new ConnectionManager($config);
$pdo = $cm->getPdo();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");

class UsersSeeder implements Seeder {
    public function run(): void {
        global $cm;
        $pdo = $cm->getPdo();
        $pdo->exec("INSERT INTO users (name) VALUES ('SeederUser')");
    }
}

$runner = new SeedRunner($cm, [UsersSeeder::class]);
$runner->runAll();

print_r($pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC));
