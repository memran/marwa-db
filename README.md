# Marwa DB

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

## Seeders

Seeders live in `database/seeders/` and implement `Marwa\DB\Seeder\Seeder`.

For the current `db:seed` auto-discovery flow, use the `Marwa\DB\Seeder` namespace unless you customize the runner.

```php
<?php

namespace Marwa\DB\Seeder;

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
