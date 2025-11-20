# Marwa-DB
[![Latest Version](https://img.shields.io/packagist/v/memran/marwa-db.svg)](https://packagist.org/packages/memran/marwa-db)
[![Total Downloads](https://img.shields.io/packagist/dt/memran/marwa-db.svg?style=flat-square)](https://packagist.org/packages/memran/marwa-db)
[![PHP Version](https://img.shields.io/packagist/php-v/memran/marwa-db)](https://php.net)
[![License](https://img.shields.io/packagist/l/memran/marwa-db)](LICENSE)

<<<<<<< HEAD
**memran/marwa-db** is a PSR-compliant, framework-agnostic, Database library built on top of PDO.  
It includes a fluent query builder,ORM, schema builder, migrations, and connection load balancing.
=======
**MarwaDB** is a PSR-compliant, framework-agnostic, Laravel-style database library built on top of PDO.  
It includes a fluent query builder, Eloquent-style ORM, schema builder, migrations, and connection load balancing.
>>>>>>> 873fc8c48edfee3c242232b2f179bbbf2f3425fc

---

## 📌 Features

- **Multiple Connections** with load balancing & retry policies
- **Fluent Query Builder** — chainable, secure, prepared statements
- **Eloquent-style ORM**:
  - Auto timestamps (`created_at`, `updated_at`)
  - Soft deletes
  - `fillable` / `guarded` attributes for mass assignment protection
  - Relationships: `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`
  - Eager loading (`with()`, `load()`)
- **Schema Builder**:
  - Create/drop tables
  - Foreign keys
  - Indexes (`primary`, `unique`, `index`)
  - Column modifiers (`nullable`, `default`, `after`)
- **Migrations CLI**:
  - `make:migration`
  - `migrate`, `migrate:rollback`, `migrate:refresh`
- **Seeder Support** with Faker
- **Debug Panel** — view executed queries & timings
- **PSR-3 Logging** integration

---

## 📦 Installation

```bash
composer require memran/marwa-db
```

---

## ⚙ Configuration

`config/database.php`

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

---

## 🚀 CLI Usage

```bash
php bin/marwa-db list
```

**Create migration:**

```bash
php bin/marwa-db make:migration create_users_table
```

**Run migrations:**

```bash
php bin/marwa-db migrate
```

**Rollback:**

```bash
php bin/marwa-db migrate:rollback
```

---

## 🛠 Query Builder Examples

```php
use Marwa\DB\Facades\DB;

$users = DB::table('users')
    ->where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();
```

```php
DB::table('users')->insert([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
]);
```

---

## 🏷 ORM Examples

```php
use App\Models\User;

// Create record
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Find & update
$user = User::find(1);
$user->email = 'new@example.com';
$user->save();

// Soft delete
$user->delete();
```

---

## 🔗 Relationships

```php
class User extends Model {
    public function posts() {
        return $this->hasMany(Post::class);
    }
}

class Post extends Model {
    public function author() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

---

## 🏗 Schema Builder

```php
use Marwa\DB\Schema\Schema;

Schema::create('users', function($table) {
    $table->increments('id');
    $table->string('name')->nullable();
    $table->string('email')->unique();
    $table->timestamps();
});
```

---

## 📋 Function Reference

### Query Builder

- `table($name)` — Selects table
- `select(...$columns)` — Selects specific columns
- `where($column, $operator, $value)` — Adds WHERE clause
- `orWhere(...)` — Adds OR WHERE clause
- `orderBy($column, $direction)` — Sort results
- `groupBy($column)` — Group results
- `limit($n)` — Limit rows
- `get()` — Fetch results
- `first()` — Fetch first row
- `insert($data)` — Insert new record(s)
- `update($data)` — Update record(s)
- `delete()` — Delete record(s)

### ORM

- `find($id)` — Find by primary key
- `all()` — Get all rows
- `create($attributes)` — Insert & return model
- `save()` — Save changes
- `delete()` — Delete (with soft delete if enabled)
- `with($relations)` — Eager load relations

### Schema Builder

- `create($table, $callback)` — Create new table
- `drop($table)` — Drop table
- Column types: `string`, `integer`, `text`, `boolean`, `timestamp`, etc.
- Modifiers: `nullable()`, `default($value)`, `after($column)`

---

## 🐞 Debugging

Enable query debug in config:

```php
'debug' => true
```

View queries:

```php
use Marwa\DB\Support\DebugPanel;
DebugPanel::render();
```

---

## 📜 License

MIT — See LICENSE for details.
