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
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL)');

for ($i = 1; $i <= 8; $i++) {
    $pdo->prepare('INSERT INTO users (name, active) VALUES (?, ?)')->execute([
        'User ' . $i,
        $i % 2 === 0 ? 1 : 0,
    ]);
}

final class User extends Model
{
    protected static ?string $table = 'users';

    protected static array $fillable = ['name', 'active'];
}

$page = User::query()
    ->orderBy('id')
    ->paginate(3, 1);

printf(
    "Page %d of %d (%d total rows)\n",
    $page['current_page'],
    $page['last_page'],
    $page['total']
);

foreach ($page['data'] as $user) {
    printf("- %s\n", $user->getAttribute('name'));
}

echo "\nChunked results:\n";

User::query()
    ->orderBy('id')
    ->chunk(3, function (int $offset, array $users): void {
        printf("Chunk starting at offset %d\n", $offset);

        foreach ($users as $user) {
            printf("  - %s\n", $user->getAttribute('name'));
        }
    });
