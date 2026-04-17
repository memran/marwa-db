# Marwa DB

[![Latest Version](https://img.shields.io/packagist/v/memran/marwa-db.svg)](https://packagist.org/packages/memran/marwa-db)
[![Total Downloads](https://img.shields.io/packagist/dt/memran/marwa-db.svg)](https://packagist.org/packages/memran/marwa-db)
[![License](https://img.shields.io/packagist/l/memran/marwa-db.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/memran/marwa-db.svg)](https://packagist.org/packages/memran/marwa-db)
[![CI](https://github.com/memran/marwa-db/actions/workflows/ci.yml/badge.svg)](https://github.com/memran/marwa-db/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/codecov/c/github/memran/marwa-db.svg)](https://codecov.io/gh/memran/marwa-db)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)

`memran/marwa-db` is a framework-agnostic PHP database toolkit built on PDO. It provides:

- connection management with pooling and retry support
- a fluent query builder
- an Active Record style ORM
- schema and migration helpers
- seeder discovery and execution
- query logging, a built-in debug panel, and optional `memran/marwa-debugbar` integration

The package is intended for plain PHP applications, small frameworks, and custom stacks that want database tooling without a full framework dependency.

## Requirements

- PHP 8.2+
- `ext-pdo`
- `ext-json`
- a supported PDO driver: MySQL, PostgreSQL, or SQLite

## Installation

Install the package:

```bash
composer require memran/marwa-db
```

Optional development debug bar:

```bash
composer require --dev memran/marwa-debugbar
```

For work inside this repository:

```bash
composer install
```

## Package Overview

Primary entry points:

- `Marwa\DB\Bootstrap`
- `Marwa\DB\Connection\ConnectionManager`
- `Marwa\DB\Facades\DB`
- `Marwa\DB\Query\Builder`
- `Marwa\DB\ORM\Model`
- `Marwa\DB\Schema\Schema`
- `Marwa\DB\Seeder\SeedRunner`

## Quick Start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Marwa\DB\Bootstrap;
use Marwa\DB\Facades\DB;
use Marwa\DB\ORM\Model;
use Marwa\DB\Schema\Schema;

$config = [
    'default' => [
        'driver' => 'sqlite',
        'database' => __DIR__ . '/database.sqlite',
        'debug' => true,
    ],
];

$manager = Bootstrap::init($config, enableDebugPanel: true);

DB::setManager($manager);
Model::setConnectionManager($manager);
Schema::init($manager);
```

At this point you can:

- build queries through `DB::table(...)`
- configure models with `Model::setConnectionManager(...)`
- run schema operations with `Schema::create(...)` and `Schema::drop(...)`
- render debugging output with `echo $manager->renderDebugBar()` when `memran/marwa-debugbar` is installed

## Configuration

The package expects a named connection array. The simplest configuration looks like this:

```php
return [
    'default' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'app',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [],
        'debug' => false,
    ],
];
```

SQLite example:

```php
return [
    'default' => [
        'driver' => 'sqlite',
        'database' => __DIR__ . '/../database/app.sqlite',
        'debug' => true,
    ],
];
```

Supported top-level connection fields:

- `driver`
- `host`
- `port`
- `database`
- `username`
- `password`
- `charset`
- `options`
- `debug`

## Bootstrap and Connection Management

### `Bootstrap::init(array $dbConfig, ?LoggerInterface $logger = null, bool $enableDebugPanel = false): ConnectionManager`

Creates the `ConnectionManager`, optionally enables debugging helpers, and stores the manager globally in `$GLOBALS['cm']`.

```php
use Marwa\DB\Bootstrap;

$manager = Bootstrap::init($config, enableDebugPanel: true);
```

When `enableDebugPanel` is `true`:

- the built-in `DebugPanel` is attached
- `QueryLogger` is attached
- `memran/marwa-debugbar` is attached automatically if installed

### `ConnectionManager`

Common public methods:

- `getPdo(?string $name = 'default'): PDO`
- `getConnection(?string $name = 'default'): PDO`
- `transaction(Closure $callback, ?string $connectionName = null): mixed`
- `setDebugPanel(?DebugPanel $panel): void`
- `getDebugPanel(): ?DebugPanel`
- `setDebugBar(?object $debugBar): void`
- `getDebugBar(): ?object`
- `renderDebugBar(): string`
- `setQueryLogger(?QueryLogger $queryLogger): void`
- `getQueryLogger(): ?QueryLogger`
- `isDebug(string $name = 'default'): bool`
- `getDriver(string $name = 'default'): string`
- `pickReplica(array $replicas): PDO`

Example:

```php
$pdo = $manager->getPdo();

$manager->transaction(function (PDO $connection): void {
    $connection->exec("INSERT INTO users (name) VALUES ('Alice')");
});
```

### Global Query Instrumentation

Query logging is captured below the query builder layer. Any SQL executed through the PDO returned by `ConnectionManager::getPdo()` is loggable:

- query builder statements
- ORM/model statements
- raw `PDO::query(...)`
- raw `PDO::exec(...)`
- prepared statements created through `PDO::prepare(...)->execute(...)`

This is package-level behavior. Application code does not need a separate wrapper around raw PDO usage.

## Query Builder

The query builder is available through `DB::table(...)` or by instantiating `Marwa\DB\Query\Builder` directly.

### `DB::setManager(ConnectionManager $cm): void`

Registers the shared manager used by the facade.

### `DB::table(string $table, string $conn = 'default'): Builder`

Starts a fluent query against a table.

### Transaction methods

- `DB::connection(?string $name = null): ConnectionManager`
- `DB::beginTransaction(string $conn = 'default'): void`
- `DB::commit(string $conn = 'default'): void`
- `DB::rollback(string $conn = 'default'): void`
- `DB::transaction(callable $callback, string $conn = 'default'): mixed`

Example:

```php
$result = DB::transaction(function () {
    User::create(['name' => 'Alice']);
    Order::create(['user_id' => 1, 'total' => 100]);
});
```

```php
use Marwa\DB\Facades\DB;

DB::setManager($manager);

$users = DB::table('users')
    ->select('id', 'email')
    ->where('status', '=', 'active')
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get();
```

### Query builder methods

Selection and table:

- `table(string $table): self`
- `from(string $table): self`
- `select(string ...$columns): self`
- `selectRaw(string $expression, array $bindings = []): self`

Filtering and ordering:

- `where(string $column, string $operator, mixed $value, string $boolean = 'and'): self`
- `orWhere(string $column, string $operator, mixed $value): self`
- `whereIn(string $column, array $values, bool $not = false, string $boolean = 'and'): self`
- `whereNotIn(string $column, array $values, string $boolean = 'and'): self`
- `whereNull(string $column, string $boolean = 'and'): self`
- `whereNotNull(string $column, string $boolean = 'and'): self`
- `whereJsonContains(string $column, mixed $value): self`
- `whereJsonLength(string $column, int $length): self`
- `whereJsonValue(string $column, string $path, mixed $value): self`
- `orderBy(string $column, string $direction = 'asc'): self`
- `limit(int $n): self`
- `offset(int $n): self`

Reading:

- `get(int $fetchMode = PDO::FETCH_ASSOC): array`
- `first(int $fetchMode = PDO::FETCH_ASSOC): array|object|null`
- `value(string $column): mixed`
- `pluck(string $column): Collection`
- `count(string $column = '*'): int`
- `max(string $column): mixed`
- `min(string $column): mixed`
- `sum(string $column): int|float|null`
- `avg(string $column): ?float`
- `paginate(int $perPage = 15, int $page = 1, int $fetchMode = PDO::FETCH_ASSOC): array`

Writing:

- `insert(array $data): int`
- `update(array $data): int`
- `delete(): int`

Debugging helpers:

- `toSql(): string`
- `getBindings(): array`
- `clear(): void`

Example:

```php
$total = DB::table('orders')
    ->where('status', '=', 'paid')
    ->sum('amount');
```

## ORM

Extend `Marwa\DB\ORM\Model` to define your models.

```php
use Marwa\DB\ORM\Model;

final class User extends Model
{
    protected static ?string $table = 'users';
    protected static array $fillable = ['name', 'email'];
}
```

Register the connection manager once:

```php
User::setConnectionManager($manager);
```

### Model Events

The Observable trait provides event hooks for model lifecycle:

- `Model::onCreating(callable $callback): void`
- `Model::onCreated(callable $callback): void`
- `Model::onUpdating(callable $callback): void`
- `Model::onUpdated(callable $callback): void`
- `Model::onSaving(callable $callback): void`
- `Model::onSaved(callable $callback): void`
- `Model::onDeleting(callable $callback): void`
- `Model::onDeleted(callable $callback): void`

Example:

```php
User::onCreated(function ($user) {
    Log::info("User created: {$user->id}");
});
```

### Common model API

Setup:

- `setTable(string $table): void`
- `setConnectionManager(ConnectionManager $cm, string $connection = 'default'): void`
- `table(): string`

Query entry points:

- `query(): Marwa\DB\ORM\QueryBuilder`
- `where(string $col, string $op, mixed $val): Marwa\DB\ORM\QueryBuilder`
- `all(): array`
- `find(int|string $id): ?static`
- `findOrFail(int|string $id): static`
- `firstOrFail(): static`
- `exists(): bool`
- `count(string $col = '*'): int`
- `chunk(int $size, callable $callback): void`
- `chunkById(int $size, callable $callback, string $idCol = 'id'): void`

Writes:

- `create(array $attributes): static`
- `save(): bool`
- `delete(): bool`
- `forceDelete(): bool`
- `restore(): bool`
- `destroy(int|array $ids): int`
- `refresh(): static`

Attribute and serialization helpers:

- `fill(array $attributes): static`
- `getDirty(): array`
- `getKey(): int|string|null`
- `getKeyName(): string`
- `getAttribute(string $key): mixed`
- `toArray(): array`
- `toJson(int $options = JSON_UNESCAPED_UNICODE): string`

Scopes and soft-delete toggles:

- `addGlobalScope(Closure $scope, ?string $identifier = null): void`
- `withoutGlobalScope(string $identifier): static`
- `withTrashed(): static`
- `onlyTrashed(): static`

### Relation classes

The package includes relation classes for eager loading:

- `HasOne` - one-to-one relationship
- `HasMany` - one-to-many relationship
- `BelongsTo` - inverse of HasMany/HasOne
- `BelongsToMany` - many-to-many via pivot table
- `MorphTo` - polymorphic (morphTo)
- `MorphMany` - polymorphic (morphMany)

Example:

```php
// In User model
public function posts(): HasMany
{
    return new HasMany(static::$cm, static::$connection, static::class, Post::class, 'user_id');
}
```

Example:

```php
$user = User::find(1);

if ($user !== null) {
    $user->fill([
        'email' => 'new@example.com',
    ])->save();
}
```

### ORM Query Builder

`Marwa\DB\ORM\QueryBuilder` is returned by `Model::query()` and hydrates records into model instances.

Common methods:

- `select(string ...$cols): self`
- `selectRaw(string $expr, array $bindings = []): self`
- `where(string $col, string $op, mixed $val): self`
- `whereIn(string $col, array $values): self`
- `orderBy(string $col, string $dir = 'asc'): self`
- `limit(int $n): self`
- `offset(int $n): self`
- `with(string ...$relations): self`
- `get(): array`
- `first(): ?Model`
- `firstOrFail(): Model`
- `exists(): bool`
- `insert(array $data): int`
- `update(array $data): int`
- `delete(): int`
- `count(string $col = '*'): int`
- `max(string $col): mixed`
- `min(string $col): mixed`
- `sum(string $col): int|float|null`
- `avg(string $col): ?float`
- `chunk(int $size, callable $callback): void`
- `chunkById(int $size, callable $callback, string $idCol = 'id'): void`
- `getBaseBuilder(): Marwa\DB\Query\Builder`

## Schema Builder

The schema layer is centered on `Marwa\DB\Schema\Schema` and `Marwa\DB\Schema\Builder`.

### `Schema::init(?ConnectionManager $cm = null, ?string $connectionName = null): void`

Initializes the static schema facade. If `$cm` is omitted, the package reads `$GLOBALS['cm']`.

### `Schema::create(string $table, callable $callback): void`

Creates a table.

### `Schema::drop(string $table): void`

Drops a table.

Example:

```php
use Marwa\DB\Schema\Schema;

Schema::init($manager);

Schema::create('posts', static function ($table): void {
    $table->increments('id');
    $table->string('title');
    $table->text('body');
    $table->timestamps();
});
```

For instance-based use:

- `Builder::useConnectionManager(ConnectionManager $cm): Builder`
- `Builder::create(string $table, Closure $callback): void`
- `Builder::table(string $table, Closure $callback): void`
- `Builder::drop(string $table): void`
- `Builder::rename(string $from, string $to): void`

### `Blueprint` column helpers

Common schema methods on the table blueprint:

- `increments()`
- `bigIncrements()`
- `uuid()`
- `uuidPrimary()`
- `string()`
- `text()`
- `mediumText()`
- `longText()`
- `integer()`
- `tinyInteger()`
- `smallInteger()`
- `bigInteger()`
- `boolean()`
- `decimal()`
- `float()`
- `double()`
- `date()`
- `dateTime()`
- `timestamp()`
- `timestamps()`
- `softDeletes()`
- `json()`
- `jsonb()`
- `binary()`
- `enum()`
- `set()`
- `foreignId()`
- `primary()`
- `unique()`
- `index()`
- `foreign()`

Blueprint methods:

- `comment(string $comment): self` - set table comment

Column modifiers are available through `ColumnDefinition`, including:

- `nullable()`
- `default()`
- `unsigned()`
- `autoIncrement()`
- `comment()` - column comment
- `primary()`
- `length()`
- `comment()`
- `unique()`
- `index()`
- `primaryKey()`

## Migrations

Migration helpers are available through the CLI and through `Marwa\DB\Schema\MigrationRepository`.

Common `MigrationRepository` methods:

- `ensureTable(): void`
- `migrate(): int`
- `rollbackLastBatch(): int`
- `rollbackAll(): int`
- `getRanWithDetails(): array`
- `getMigrationFiles(): array`

Migration files generated by the package return an anonymous class extending `Marwa\DB\CLI\AbstractMigration`.

Example generated structure:

```php
<?php

use Marwa\DB\CLI\AbstractMigration;
use Marwa\DB\Schema\Schema;

return new class extends AbstractMigration {
    public function up(): void
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('users');
    }
};
```

## Seeders

Seeder execution is handled by `Marwa\DB\Seeder\SeedRunner`.

### `SeedRunner`

Constructor:

```php
new SeedRunner(
    cm: $manager,
    logger: null,
    connection: 'default',
    seedPath: __DIR__ . '/database/seeders',
    seedNamespace: 'Database\\Seeders',
);
```

Public methods:

- `runAll(bool $wrapInTransaction = true, ?array $only = null, array $except = []): void`
- `runOne(string $fqcn, bool $wrapInTransaction = true): void`
- `discoverSeeders(): array`

Example:

```php
use Marwa\DB\Seeder\SeedRunner;

$runner = new SeedRunner($manager);
$runner->runAll();
```

Seeder classes implement `Marwa\DB\Seeder\Seeder`:

```php
use Marwa\DB\Seeder\Seeder;

final class UsersTableSeeder implements Seeder
{
    public function run(): void
    {
        // seed logic
    }
}
```

## Debugging and Query Inspection

### Built-in debug panel

Enable it during bootstrap:

```php
$manager = Bootstrap::init($config, enableDebugPanel: true);
```

Render the built-in panel:

```php
echo $manager->getDebugPanel()?->render();
```

Public `DebugPanel` methods:

- `addQuery(string $sql, array $bindings, float $timeMs, string $connection = 'default', ?string $error = null): void`
- `all(): array`
- `clear(): void`
- `render(): string`

### Optional `memran/marwa-debugbar`

If `memran/marwa-debugbar` is installed as a dev dependency, `Bootstrap::init(..., enableDebugPanel: true)` will attach it automatically.

Render through the manager:

```php
echo $manager->renderDebugBar();
```

Render through the package helper:

```php
echo \Marwa\DB\Support\db_debugbar();
```

The helper reads the global manager from `$GLOBALS['cm']` when no explicit manager is passed.

### Query logger

`Marwa\DB\Logger\QueryLogger` stores query records in memory and can mirror them to a PSR-3 logger.

Public methods:

- `log(string $sql, array $bindings, float $timeMs, string $connection, ?string $error = null): void`
- `all(): array`
- `clear(): void`

## CLI

The repository includes a Symfony Console entrypoint:

```bash
php bin/marwa-db list
```

Available commands:

- `migrate`
- `migrate:rollback`
- `migrate:refresh`
- `migrate:status`
- `make:migration`
- `make:seeder`
- `db:seed`

Examples:

```bash
php bin/marwa-db migrate
php bin/marwa-db migrate:status
php bin/marwa-db make:migration create_users_table
php bin/marwa-db make:seeder UsersTableSeeder
php bin/marwa-db db:seed
php bin/marwa-db db:seed --only=UsersTableSeeder
```

## Testing and Quality

Run the full test suite:

```bash
composer test
```

Run unit tests only:

```bash
composer test:unit
```

Run integration tests:

```bash
composer test:integration
```

Run static analysis:

```bash
composer run analyse
```

Run syntax linting:

```bash
composer lint
```

Run the standard CI gate locally:

```bash
composer run ci
```

## Integration Notes

- `DB::setManager(...)` must be called before using the `DB` facade.
- `Model::setConnectionManager(...)` must be called before using the ORM.
- `Schema::init(...)` must be called before using the static schema facade unless you rely on the global manager created by `Bootstrap::init(...)`.
- Query logging is automatic for all SQL executed through `ConnectionManager::getPdo()`.
- `memran/marwa-debugbar` is optional and intended for development use.

## Security Notes

- Do not commit production credentials.
- Keep debug tooling disabled outside trusted environments.
- Prefer configuration loaded from environment-aware application code.
- Treat rendered debug output as sensitive because it may contain SQL, bindings, request state, and exception details.

## License

MIT. See [LICENSE](LICENSE).
