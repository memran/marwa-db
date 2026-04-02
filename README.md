# Marwa DB

[![Latest Version](https://img.shields.io/packagist/v/memran/marwa-db.svg)](https://packagist.org/packages/memran/marwa-db)
[![Total Downloads](https://img.shields.io/packagist/dt/memran/marwa-db.svg)](https://packagist.org/packages/memran/marwa-db)
[![License](https://img.shields.io/packagist/l/memran/marwa-db.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/memran/marwa-db.svg)](https://packagist.org/packages/memran/marwa-db)
[![CI](https://github.com/memran/marwa-db/actions/workflows/ci.yml/badge.svg)](https://github.com/memran/marwa-db/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/codecov/c/github/memran/marwa-db.svg)](https://codecov.io/gh/memran/marwa-db)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)

Marwa DB is a framework-agnostic PHP database toolkit built on PDO. It provides connection management, a fluent query builder, an Active Record style ORM, schema helpers, migrations, seeders, a CLI, and lightweight debugging utilities for standalone apps or framework integrations.

## Requirements

- PHP 8.2 or newer
- `ext-pdo`
- `ext-json`
- A supported database driver: MySQL, PostgreSQL, or SQLite

## Installation

```bash
composer require memran/marwa-db
```

For development inside this repository:

```bash
composer install
```

## Features

- Connection pooling and retry handling
- Fluent query builder with prepared statements
- ORM with timestamps, casts, soft deletes, mass assignment control, and relations
- Schema builder for create, alter, rename, and drop operations
- Migration and seeder command support
- Debug panel and query logging helpers

## Quick Start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Marwa\DB\Bootstrap;
use Marwa\DB\Facades\DB;
use Marwa\DB\ORM\Model;
use Marwa\DB\Schema\Schema;

$config = require __DIR__ . '/config/database.php';

$manager = Bootstrap::init($config);

DB::setManager($manager);
Model::setConnectionManager($manager);
Schema::init($manager);
```

## Usage Examples

### Query Builder

```php
$users = DB::table('users')
    ->select('id', 'email')
    ->where('status', '=', 'active')
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get();
```

### ORM

```php
$user = App\Models\User::find(1);

if ($user !== null) {
    $user->email = 'new@example.com';
    $user->save();
}
```

### Schema Builder

```php
Schema::create('posts', static function ($table): void {
    $table->increments('id');
    $table->string('title');
    $table->text('body');
    $table->timestamps();
});
```

### Seeders

```php
use Marwa\DB\Seeder\SeedRunner;

$runner = new SeedRunner($manager);
$runner->runAll();
```

## Configuration Guide

The package expects a connection array. The recommended shape is:

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
        'retry' => 3,
        'retry_delay' => 300,
        'debug' => false,
    ],
];
```

Supported keys:

- `driver`
- `host`
- `port`
- `database`
- `username`
- `password`
- `charset`
- `options`
- `retry`
- `retry_delay`
- `debug`

SQLite example:

```php
return [
    'default' => [
        'driver' => 'sqlite',
        'database' => __DIR__ . '/../database/app.sqlite',
        'debug' => false,
    ],
];
```

Legacy configuration shape is still supported:

```php
return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ],
];
```

## Project Layout

- `src/` core library code
- `bin/marwa-db` CLI entrypoint
- `config/database.php` default connection config
- `database/migrations/` user migrations
- `database/seeders/` user seeders
- `tests/` unit and integration tests
- `example/` runnable examples

## Testing

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
MARWA_DB_INTEGRATION=1 composer test:integration
```

Run coverage output:

```bash
composer test:coverage
```

This requires Xdebug or another supported coverage driver.

## Static Analysis

Run PHPStan with the repository configuration:

```bash
composer analyse
```

`composer analyze` is kept as an alias for compatibility.

## Linting

Run the built-in PHP syntax check across tracked PHP files:

```bash
composer lint
```

## CLI

Inspect available commands:

```bash
php bin/marwa-db list
```

## CI/CD

The repository is intended to run in GitHub Actions with a simple quality gate:

- `composer lint`
- `composer analyse`
- `composer test`

The workflow can also run coverage on a dedicated job or matrix entry.

## Security Notes

- Do not commit real credentials to `config/database.php`.
- Prefer environment-specific config loading in production.
- Keep migration and seeder code under source control, but never hard-code secrets into them.
- Use prepared statements for user input; the query builder already binds values for normal `where`, `insert`, `update`, and `delete` flows.

## Contribution Guide

1. Create a focused branch.
2. Add or update tests for behavior changes.
3. Run `composer lint`, `composer analyse`, and `composer test`.
4. Keep public API changes documented in this README.
5. Use short imperative commit messages such as `Fix query builder state leaks`.

## License

MIT. See [LICENSE](LICENSE).
