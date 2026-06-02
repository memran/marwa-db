<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;

$config = new Config([
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ],
]);

$cm = new ConnectionManager($config);
Model::setConnectionManager($cm, 'sqlite');

$pdo = $cm->getPdo('sqlite');
$pdo->exec('CREATE TABLE accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, balance INTEGER NOT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
$pdo->exec("INSERT INTO accounts (name, balance) VALUES ('Alice', 100)");
$pdo->exec("INSERT INTO accounts (name, balance) VALUES ('Bob', 100)");

final class Account extends Model
{
    protected static ?string $table = 'accounts';

    protected static array $fillable = ['name', 'balance'];
}

$cm->transaction(function (\PDO $pdo): void {
    $pdo->exec('UPDATE accounts SET balance = balance - 25 WHERE id = 1');
    $pdo->exec('UPDATE accounts SET balance = balance + 25 WHERE id = 2');
}, 'sqlite');

$alice = Account::find(1);
if ($alice !== null) {
    $alice->fill(['balance' => 75])->save();
}

$bob = Account::query()
    ->where('name', '=', 'Bob')
    ->first();

if ($bob !== null) {
    $bob->fill(['balance' => 125])->save();
}

$rows = Account::query()
    ->orderBy('id')
    ->get();

foreach ($rows as $account) {
    printf(
        "%s: %d\n",
        $account->getAttribute('name'),
        (int) $account->getAttribute('balance')
    );
}
