<?php
require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;

$config = new Config([
    'default' => 'sqlite',
    'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']],
]);

$cm = new ConnectionManager($config);
$pdo = $cm->getPdo();
$pdo->exec("CREATE TABLE accounts (id INTEGER PRIMARY KEY, balance INT)");

$pdo->exec("INSERT INTO accounts (balance) VALUES (100)");
$pdo->exec("INSERT INTO accounts (balance) VALUES (100)");

try {
    $cm->transaction(function($pdo) {
        $pdo->exec("UPDATE accounts SET balance = balance - 50 WHERE id = 1");
        $pdo->exec("UPDATE accounts SET balance = balance + 50 WHERE id = 2");
    });
    echo "Transfer success!\n";
} catch (Exception $e) {
    echo "Transfer failed: ".$e->getMessage()."\n";
}

print_r($pdo->query("SELECT * FROM accounts")->fetchAll(PDO::FETCH_ASSOC));
