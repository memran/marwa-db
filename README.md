# Marwa DB

[![CI](https://github.com/memran/marwa-db/actions/workflows/ci.yml/badge.svg)](https://github.com/memran/marwa-db/actions/workflows/ci.yml)
[![PHPUnit](https://img.shields.io/badge/tests-PHPUnit%2010-0E9F6E)](https://phpunit.de/)
[![PHPStan](https://img.shields.io/badge/static%20analysis-PHPStan-6C43E0)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/packagist/php-v/memran/marwa-db)](https://packagist.org/packages/memran/marwa-db)
[![Latest Version](https://img.shields.io/packagist/v/memran/marwa-db.svg)](https://packagist.org/packages/memran/marwa-db)
[![Downloads](https://img.shields.io/packagist/dt/memran/marwa-db.svg)](https://packagist.org/packages/memran/marwa-db)
[![License](https://img.shields.io/packagist/l/memran/marwa-db)](LICENSE)

`Marwa DB` is a lightweight, framework-agnostic PHP database toolkit built on top of PDO. It provides a fluent query builder, an Eloquent-style ORM, schema helpers, migrations, seeders, and a simple debug panel.

## Features

- PDO-based connection management
- Fluent query builder with prepared statements
- Active Record style ORM with:
  - timestamps
  - soft deletes
  - fillable / guarded protection
  - relationship helpers
- Schema builder for tables, indexes, and foreign keys
- CLI tools for migrations and seeders
- Query inspection through a small debug panel

## Requirements

- PHP `^8.1`
- PDO extension
- JSON extension

## Installation

```bash
composer require memran/marwa-db
```

## Using In Another Project Or Framework

You can use `Marwa DB` inside a plain PHP app or integrate it into another framework as a standalone database layer.

Typical setup steps:

1. Install the package with Composer.
2. Create a database config array or config file.
3. Bootstrap a `ConnectionManager`.
4. Register the manager with `DB`, `Model`, and `Schema` if you use those APIs.
5. Point migrations and seeders to your application's `database/` directory.

Minimal bootstrap example:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Marwa\DB\Bootstrap;
use Marwa\DB\Facades\DB;
use Marwa\DB\ORM\Model;
use Marwa\DB\Schema\Schema;

$config = [
    'default' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'app',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'retry' => 3,
        'retry_delay' => 300,
        'debug' => false,
    ],
];

$manager = Bootstrap::init($config, null, false);

DB::setManager($manager);
Model::setConnectionManager($manager);
Schema::init($manager);
```

In a framework, this usually belongs in a service provider, bootstrap file, or container registration step.

## Configuration Reference

The package expects a connection array. The most direct format is:

```php
[
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
]
```

Supported keys:

- `driver`: currently `mysql` or `sqlite`
- `host`: MySQL host
- `port`: MySQL port
- `database`: database name for MySQL, or path / `:memory:` for SQLite
- `username`: MySQL username
- `password`: MySQL password
- `charset`: charset for MySQL connections
- `options`: extra PDO options
- `retry`: retry count for connection attempts
- `retry_delay`: delay between retries in milliseconds
- `debug`: enables query debug collection

SQLite example:

```php
[
    'default' => [
        'driver' => 'sqlite',
        'database' => __DIR__ . '/database/app.sqlite',
        'debug' => false,
    ],
]
```

Legacy config shape is also supported:

```php
[
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ],
]
```

## Project Layout

- `src/` core library code
- `bin/marwa-db` CLI entrypoint
- `config/database.php` connection settings
- `database/migrations/` migration files
- `database/seeders/` seeders
- `app/Models/` sample models
- `example/` runnable usage examples

## Configuration

Create or update `config/database.php`:

```php
<?php

return [
    'default' => [
        'driver'      => 'mysql',
        'host'        => '127.0.0.1',
        'port'        => 3306,
        'database'    => 'app',
        'username'    => 'root',
        'password'    => '',
        'charset'     => 'utf8mb4',
        'retry'       => 3,
        'retry_delay' => 300,
        'debug'       => true,
    ],
];
```

For another project, you can keep this config in:

- `config/database.php`
- environment-driven bootstrap code
- a framework service container binding
- a package-specific config class

## Recommended Application Structure

When using this package in another project, this layout works well:

- `config/database.php`
- `app/Models/`
- `database/migrations/`
- `database/seeders/`
- `bootstrap/db.php`

Example `bootstrap/db.php`:

```php
<?php

use Marwa\DB\Bootstrap;
use Marwa\DB\Facades\DB;
use Marwa\DB\ORM\Model;
use Marwa\DB\Schema\Schema;

$config = require __DIR__ . '/../config/database.php';

$manager = Bootstrap::init($config, null, false);

DB::setManager($manager);
Model::setConnectionManager($manager);
Schema::init($manager);

return $manager;
```

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

$users = DB::table('users')
    ->where('active', '=', 1)
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();
```

## Query Builder

```php
use Marwa\DB\Facades\DB;

$user = DB::table('users')
    ->where('email', '=', 'jane@example.com')
    ->first();

DB::table('users')->insert([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'active' => 1,
]);

DB::table('users')
    ->where('id', '=', 10)
    ->update(['active' => 0]);
```

Common methods:

- `table()`
- `select()`, `selectRaw()`
- `where()`, `orWhere()`, `whereIn()`, `whereNull()`
- `orderBy()`, `limit()`, `offset()`
- `get()`, `first()`, `value()`, `pluck()`
- `insert()`, `update()`, `delete()`
- `count()`, `min()`, `max()`, `sum()`, `avg()`

## ORM

Example model:

```php
<?php

namespace App\Models;

use Marwa\DB\ORM\Model;

class User extends Model
{
    protected static ?string $table = 'users';
    protected static array $fillable = ['name', 'email'];
}
```

Usage:

```php
use App\Models\User;
use Marwa\DB\ORM\Model;

$config = require __DIR__ . '/config/database.php';
$manager = \Marwa\DB\Bootstrap::init($config);
Model::setConnectionManager($manager);

$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

$found = User::find(1);
$found?->fill(['name' => 'Updated Name'])->save();
```

## Relationships

```php
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}

class Post extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

## Schema Builder

```php
use Marwa\DB\Schema\Schema;

Schema::init($manager);

Schema::create('users', function ($table) {
    $table->bigIncrements('id');
    $table->string('name', 100)->nullable();
    $table->string('email', 190)->unique();
    $table->timestamps();
    $table->softDeletes();
});
```

## CLI Commands

List commands:

```bash
php bin/marwa-db list
```

Common commands:

```bash
php bin/marwa-db make:migration create_users_table
php bin/marwa-db migrate
php bin/marwa-db migrate:rollback
php bin/marwa-db migrate:refresh
php bin/marwa-db migrate:status
php bin/marwa-db make:seeder UsersTableSeeder
php bin/marwa-db db:seed
```

If you are using this package outside this repository, you can still use the same CLI flow by exposing your own script that boots your app config and points to your project's `database/migrations` directory.

## Seeders

Seeders live in `database/seeders/` and implement `Marwa\DB\Seeder\Seeder`.

`db:seed` auto-discovers concrete seeders from the seeder directory, so you can organize them under your preferred application namespace.

If you prefer a shared base class for project conventions, extend `Marwa\DB\Seeder\AbstractSeeder`.

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Marwa\DB\Seeder\Seeder;

final class UsersTableSeeder implements Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);
    }
}
```

## Migrations

Migration files live in `database/migrations/` and should return a class extending `Marwa\DB\CLI\AbstractMigration`.

```php
<?php

use Marwa\DB\CLI\AbstractMigration;
use Marwa\DB\Schema\Schema;

return new class extends AbstractMigration {
    public function up(): void
    {
        Schema::create('posts', function ($table) {
            $table->bigIncrements('id');
            $table->string('title', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('posts');
    }
};
```

## Utility Classes

The package also exposes a few small helper classes for library consumers:

- `Marwa\DB\Query\Pagination` for building simple paginated payloads
- `Marwa\DB\Query\Grammar` for identifier wrapping and placeholder generation
- `Marwa\DB\Logger\QueryLogger` if you want to capture executed query metadata in your application
- package exception classes under `Marwa\DB\Exceptions\...` for custom error handling
- support helpers under `Marwa\DB\Support\...`

### Pagination

```php
use Marwa\DB\Query\Pagination;

$pagination = new Pagination();

$payload = $pagination->make(
    rows: [['id' => 1], ['id' => 2]],
    total: 12,
    perPage: 5,
    page: 2
);
```

Example result:

```php
[
    'data' => [['id' => 1], ['id' => 2]],
    'total' => 12,
    'per_page' => 5,
    'current_page' => 2,
    'last_page' => 3,
]
```

### Query Grammar

```php
use Marwa\DB\Query\Grammar;

$grammar = new Grammar();

$column = $grammar->wrap('users.email');      // `users.email`
$placeholders = $grammar->parameterize([1, 2, 3]); // ?, ?, ?
```

### Query Logger

```php
use Marwa\DB\Logger\QueryLogger;

$queryLogger = new QueryLogger();

$queryLogger->log(
    'SELECT * FROM users WHERE id = ?',
    [10],
    1.42,
    'default'
);

$entries = $queryLogger->all();
```

### Debug Panel

```php
use Marwa\DB\Support\DebugPanel;

$panel = new DebugPanel();
$panel->addQuery('SELECT * FROM users', [], 0.85);

echo $panel->render();
```

### Collection

```php
use Marwa\DB\Support\Collection;

$collection = new Collection([
    ['name' => 'A', 'score' => 10],
    ['name' => 'B', 'score' => 25],
]);

$total = $collection->sum('score');
$average = $collection->avg('score');
$first = $collection->first();
$filtered = $collection->filter(fn ($row) => $row['score'] > 10)->toArray();
```

### Arr

```php
use Marwa\DB\Support\Arr;

$email = Arr::get($_POST, 'email', 'guest@example.com');
```

### Str

```php
use Marwa\DB\Support\Str;

$value = Str::camel('user_profile_name'); // userProfileName
```

### Helpers

```php
use Marwa\DB\Support\Helpers;

$timestamp = Helpers::now(); // 2026-03-28 18:00:00
```

### Exceptions

Use the package exception classes if you want a library-specific error contract in your application code:

```php
use Marwa\DB\Exceptions\QueryException;

throw new QueryException('Invalid query state.');
```

## Debug Panel

Enable query debugging in configuration:

```php
'debug' => true,
```

Render the panel:

```php
use Marwa\DB\Support\DebugPanel;

echo $manager->getDebugPanel()?->render();
```

If you bootstrap with `Bootstrap::init($config, null, true)`, a debug panel instance is attached automatically.

## Development

Run linting:

```bash
composer lint
```

Run tests:

```bash
composer test
```

or

```bash
./vendor/bin/phpunit
```

## License

MIT. See [LICENSE](LICENSE).
