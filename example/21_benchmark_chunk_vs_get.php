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
$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    active INTEGER NOT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
)');

$stmt = $pdo->prepare('INSERT INTO users (name, active) VALUES (?, ?)');
for ($i = 1; $i <= 1000; $i++) {
    $stmt->execute([
        'User ' . $i,
        $i % 2 === 0 ? 1 : 0,
    ]);
}

final class User extends Model
{
    protected static ?string $table = 'users';
}

/**
 * @return array{label:string,total_ms:float,per_run_ms:float}
 */
function measure(string $label, callable $callback, int $iterations = 25): array
{
    $start = hrtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $callback();
    }

    $elapsedMs = (hrtime(true) - $start) / 1_000_000;

    return [
        'label' => $label,
        'total_ms' => $elapsedMs,
        'per_run_ms' => $elapsedMs / max(1, $iterations),
    ];
}

$results = [
    measure('ORM get()', function (): void {
        User::query()
            ->where('active', '=', 1)
            ->orderBy('id')
            ->get();
    }),
    measure('ORM chunk()', function (): void {
        User::query()
            ->where('active', '=', 1)
            ->orderBy('id')
            ->chunk(100, function (int $offset, array $users): void {
                unset($offset, $users);
            });
    }),
];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Chunk vs Get Benchmark</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 24px; line-height: 1.5; }
        table { border-collapse: collapse; width: 100%; max-width: 760px; margin: 16px 0; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        th { background: #f5f5f5; }
        code { background: #f4f4f4; padding: 2px 4px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Chunk vs Get Benchmark</h1>
    <p>Use <code>chunk()</code> when you need to process large result sets in smaller batches.</p>

    <table>
        <thead>
        <tr>
            <th>Benchmark</th>
            <th>Total</th>
            <th>Per run</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $result): ?>
            <tr>
                <td><?= htmlspecialchars($result['label'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format($result['total_ms'], 2) ?> ms</td>
                <td><?= number_format($result['per_run_ms'], 4) ?> ms</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
