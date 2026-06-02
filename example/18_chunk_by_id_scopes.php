<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use Marwa\DB\Query\Builder as BaseBuilder;

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
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL, votes INTEGER NOT NULL, created_at TEXT NULL, updated_at TEXT NULL)');

for ($i = 1; $i <= 6; $i++) {
    $pdo->prepare('INSERT INTO users (name, active, votes) VALUES (?, ?, ?)')->execute([
        'User ' . $i,
        $i % 2 === 0 ? 1 : 0,
        $i * 10,
    ]);
}

final class User extends Model
{
    protected static ?string $table = 'users';

    protected static array $fillable = ['name', 'active', 'votes'];

    public function scopeActive(BaseBuilder $query): void
    {
        $query->where('active', '=', 1);
    }

    public function scopePopular(BaseBuilder $query): void
    {
        $query->where('votes', '>', 20);
    }
}

echo "Scopes:\n";
$popular = User::active()->popular()->orderBy('id')->get();
foreach ($popular as $user) {
    printf("- %s (%d votes)\n", $user->getAttribute('name'), (int) $user->getAttribute('votes'));
}

echo "\nChunk by ID:\n";
User::query()->orderBy('id')->chunkById(2, function (int $lastId, array $users): void {
    printf("Chunk ending at %d\n", $lastId);

    foreach ($users as $user) {
        printf("  - %s\n", $user->getAttribute('name'));
    }
});
