# Architecture

## Layers

- `Marwa\DB\Bootstrap` creates the `ConnectionManager`
- `Marwa\DB\Connection\ConnectionManager` owns PDO connections, retry policy, and connection selection
- `Marwa\DB\Facades\DB` exposes the query facade
- `Marwa\DB\Query\Builder` handles raw SQL generation and execution
- `Marwa\DB\ORM\Model` is the Active Record base model
- `Marwa\DB\ORM\QueryBuilder` bridges database queries and model hydration
- `Marwa\DB\Schema\Schema` builds tables and runs schema changes
- `Marwa\DB\Seeder\SeedRunner` discovers and runs seeders
- `Marwa\DB\CLI\ConsoleKernel` wires CLI commands

## Runtime flow

1. Initialize the connection manager with `Bootstrap::init($config)`.
2. Register the manager on `DB`, `Model`, and `Schema`.
3. Build queries through `DB::table()` or `Model::query()`.
4. Let the query builder apply filters, joins, eager loading, and aggregates.
5. Hydrate ORM rows into model instances only at the ORM boundary.

## ORM flow

- `Model::query()` returns `Marwa\DB\ORM\QueryBuilder`
- `QueryBuilder` delegates low-level SQL work to `Marwa\DB\Query\Builder`
- `QueryBuilder::get()` / `first()` hydrate rows into models
- Relations are resolved lazily through `Model::__get()` and eagerly through `with()`
- `withCount()` augments hydrated models with related counts

## Relation model

- Keep explicit relation classes under `src/ORM/Relations/`
- Use `HasOne`, `HasMany`, `BelongsTo`, `BelongsToMany`, `MorphTo`, and `MorphMany`
- Preserve explicit relation metadata when the relation needs pivot keys or polymorphic keys
- Use shorthand relation inference only when the related model shares the parent namespace

## Source map

- Entry points: `src/Bootstrap.php`, `src/Facades/DB.php`, `src/ORM/Model.php`, `src/Schema/Schema.php`
- Core database layer: `src/Query/Builder.php`, `src/Connection/ConnectionManager.php`
- ORM layer: `src/ORM/QueryBuilder.php`, `src/ORM/Relations/`
- Database helpers: `src/Seeder/`, `src/CLI/`, `src/Support/`
