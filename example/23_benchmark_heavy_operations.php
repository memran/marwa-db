<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

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

$pdo = $cm->getPdo('sqlite');
$pdo->exec('CREATE TABLE mass_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    active INTEGER NOT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
)');
$pdo->exec('CREATE TABLE read_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    active INTEGER NOT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
)');
$pdo->exec('CREATE TABLE read_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
)');

final class MassUser extends Model
{
    protected static ?string $table = 'mass_users';
    protected static array $fillable = ['name', 'active'];
}

final class ReadUser extends Model
{
    protected static ?string $table = 'read_users';
    protected static array $fillable = ['name', 'active'];

    public function posts(): HasMany
    {
        return $this->hasMany(ReadPost::class, 'user_id');
    }
}

final class ReadPost extends Model
{
    protected static ?string $table = 'read_posts';
    protected static array $fillable = ['user_id', 'title'];
}

/**
 * @return array{label:string,units:int,total_ms:float,per_unit_ms:float}
 */
function runBenchmark(string $label, callable $callback): array
{
    $start = hrtime(true);
    $units = (int) $callback();
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;

    return [
        'label' => $label,
        'units' => $units,
        'total_ms' => $elapsedMs,
        'per_unit_ms' => $elapsedMs / max(1, $units),
    ];
}

function seedReadData(ConnectionManager $cm): void
{
    $pdo = $cm->getPdo('sqlite');
    $pdo->exec('DELETE FROM read_posts');
    $pdo->exec('DELETE FROM read_users');

    $userStmt = $pdo->prepare('INSERT INTO read_users (name, active) VALUES (?, ?)');
    for ($i = 1; $i <= 1000; $i++) {
        $userStmt->execute(['Reader ' . $i, $i % 2 === 0 ? 1 : 0]);
    }

    $postStmt = $pdo->prepare('INSERT INTO read_posts (user_id, title) VALUES (?, ?)');
    for ($i = 1; $i <= 3000; $i++) {
        $postStmt->execute([(($i - 1) % 1000) + 1, 'Post ' . $i]);
    }
}

function clearMassUsers(ConnectionManager $cm): void
{
    $cm->getPdo('sqlite')->exec('DELETE FROM mass_users');
}

function transaction(ConnectionManager $cm, callable $callback): void
{
    $pdo = $cm->getPdo('sqlite');
    $pdo->beginTransaction();

    try {
        $callback();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

MassUser::setConnectionManager($cm, 'sqlite');

$results = [];

$results[] = runBenchmark('Raw builder mass insert', function () use ($cm): int {
    clearMassUsers($cm);

    transaction($cm, function () use ($cm): void {
        for ($i = 1; $i <= 500; $i++) {
            DB::table('mass_users', 'sqlite')->insert([
                'name' => 'Raw User ' . $i,
                'active' => $i % 2,
            ]);
        }
    });

    return 500;
});

$results[] = runBenchmark('ORM create mass insert', function () use ($cm): int {
    clearMassUsers($cm);

    transaction($cm, function (): void {
        for ($i = 1; $i <= 500; $i++) {
            MassUser::create([
                'name' => 'ORM User ' . $i,
                'active' => $i % 2,
            ]);
        }
    });

    return 500;
});

seedReadData($cm);
MassUser::setConnectionManager($cm, 'sqlite');
ReadUser::setConnectionManager($cm, 'sqlite');
ReadPost::setConnectionManager($cm, 'sqlite');

$results[] = runBenchmark('ORM chunk()', function (): int {
    $processed = 0;
    ReadUser::query()
        ->where('active', '=', 1)
        ->orderBy('id')
        ->chunk(100, function (int $offset, array $rows) use (&$processed): void {
            $processed += count($rows);
            unset($offset);
        });

    return $processed;
});

$results[] = runBenchmark('ORM chunkById()', function (): int {
    $processed = 0;
    ReadUser::query()
        ->where('active', '=', 1)
        ->chunkById(100, function (int|string $lastId, array $rows) use (&$processed): void {
            $processed += count($rows);
            unset($lastId);
        });

    return $processed;
});

$results[] = runBenchmark('ORM withCount()', function (): int {
    return count(ReadUser::query()
        ->withCount('posts')
        ->orderBy('id')
        ->get());
});

$results[] = runBenchmark('ORM withCount() aliased', function (): int {
    return count(ReadUser::query()
        ->withCount('posts', 'posts as total_posts')
        ->orderBy('id')
        ->get());
});

$results[] = runBenchmark('ORM eager load posts', function (): int {
    return count(ReadUser::query()
        ->with('posts')
        ->limit(100)
        ->get());
});

$results[] = runBenchmark('ORM lazy load posts', function (): int {
    $users = ReadUser::query()
        ->limit(100)
        ->get();

    foreach ($users as $user) {
        $user->posts()->get($user);
    }

    return count($users);
});

$results[] = runBenchmark('ORM paginate() first page', function (): int {
    return count(ReadUser::query()
        ->where('active', '=', 1)
        ->paginate(50, 1)['data']);
});

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Heavy Operations Benchmark</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 24px; line-height: 1.5; }
        table { border-collapse: collapse; width: 100%; max-width: 880px; margin: 16px 0; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        th { background: #f5f5f5; }
        code { background: #f4f4f4; padding: 2px 4px; border-radius: 4px; }
        .note { color: #555; }
    </style>
</head>
<body>
    <h1>Heavy Operations Benchmark</h1>
    <p class="note">This page compares heavier write and traversal workloads on SQLite in memory.</p>

    <table>
        <thead>
        <tr>
            <th>Benchmark</th>
            <th>Units</th>
            <th>Total</th>
            <th>Per row</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $result): ?>
            <tr>
                <td><?= htmlspecialchars($result['label'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format($result['units']) ?></td>
                <td><?= number_format($result['total_ms'], 2) ?> ms</td>
                <td><?= number_format($result['per_unit_ms'], 4) ?> ms</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p class="note">
        Benchmarks included: <code>insert</code>, <code>create</code>, <code>chunk</code>, <code>chunkById</code>, <code>paginate</code>, <code>withCount</code>, aliased counts, and relation loading.
    </p>
    <p class="note">
        Interpretation: this is a relative SQLite in-memory benchmark. Use it to compare query shapes and ORM overhead, not as a production latency target.
    </p>
</body>
</html>
