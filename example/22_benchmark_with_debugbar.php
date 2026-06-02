<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DebugBar\Collectors\DbQueryCollector;
use Marwa\DebugBar\Collectors\KpiCollector;
use Marwa\DebugBar\Collectors\LogCollector;
use Marwa\DebugBar\Collectors\MemoryCollector;
use Marwa\DebugBar\Collectors\TimelineCollector;
use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Renderer;
use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Facades\DB;
use Marwa\DB\ORM\Model;
use Marwa\DB\ORM\Relations\HasMany;

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
DB::setManager($cm);
Model::setConnectionManager($cm, 'sqlite');

$bar = new DebugBar(true);
$bar->collectors()->register(KpiCollector::class);
$bar->collectors()->register(TimelineCollector::class);
$bar->collectors()->register(LogCollector::class);
$bar->collectors()->register(DbQueryCollector::class);
$bar->collectors()->register(MemoryCollector::class);

$bar->mark('bootstrap');

$pdo = $cm->getPdo('sqlite');
$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    active INTEGER NOT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
)');

$pdo->exec('CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
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

$postStmt = $pdo->prepare('INSERT INTO posts (user_id, title) VALUES (?, ?)');
for ($i = 1; $i <= 1000; $i++) {
    $postStmt->execute([
        (($i - 1) % 1000) + 1,
        'Post ' . $i,
    ]);
}

final class User extends Model
{
    protected static ?string $table = 'users';

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}

final class Post extends Model
{
    protected static ?string $table = 'posts';
}

function measure(DebugBar $bar, string $label, callable $callback, int $iterations = 50): array
{
    $bar->mark($label . ':start');

    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $callback();
    }
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;

    $bar->mark($label . ':end');
    $bar->log('info', $label, ['elapsed_ms' => $elapsedMs, 'iterations' => $iterations]);

    return [
        'label' => $label,
        'elapsed_ms' => $elapsedMs,
        'per_run_ms' => $elapsedMs / max(1, $iterations),
    ];
}

$results = [
    measure($bar, 'raw_query', function (): void {
        DB::table('users', 'sqlite')
            ->where('active', '=', 1)
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    }),
    measure($bar, 'orm_query', function (): void {
        User::query()
            ->where('active', '=', 1)
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    }),
    measure($bar, 'orm_with_count', function (): void {
        User::query()
            ->withCount('posts')
            ->limit(25)
            ->get();
    }, 10),
];

$bar->mark('render');

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Marwa DB Benchmark with DebugBar</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 24px; line-height: 1.5; }
        table { border-collapse: collapse; width: 100%; max-width: 900px; margin: 16px 0 24px; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        th { background: #f5f5f5; }
        code { background: #f4f4f4; padding: 2px 4px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Marwa DB Benchmark with DebugBar</h1>
    <p>This page measures a few query paths and shows the timeline in the debug bar below.</p>

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
                <td><?= number_format($result['elapsed_ms'], 2) ?> ms</td>
                <td><?= number_format($result['per_run_ms'], 4) ?> ms</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p>Marks recorded: <code>bootstrap</code>, <code>raw_query:start</code>, <code>raw_query:end</code>, <code>orm_query:start</code>, <code>orm_query:end</code>, <code>orm_with_count:start</code>, <code>orm_with_count:end</code>, <code>render</code>.</p>

    <?= (new Renderer($bar))->render(); ?>
</body>
</html>
