---
name: marwa-db
description: Use when working with the Marwa DB PHP database toolkit, including query builder, ORM models and relations, schema builder, migrations, seeders, CLI commands, examples, tests, or documentation updates.
---

# Marwa DB

Use this skill when the task touches Marwa DB code or examples.

Read these references when needed:

- `references/architecture.md` for the system layout and initialization flow
- `references/api.md` for the public API surface and class-level entry points
- `references/examples.md` for short ORM and query builder patterns

## Workflow

1. Read the closest source of truth first: `README.md`, `references/architecture.md`, `references/api.md`, `src/`, `tests/`, and `example/`.
2. Keep changes backward-compatible unless the user explicitly asks for a breaking change.
3. Prefer the existing architecture:
   - `DB::table()` and `Marwa\DB\Query\Builder` for raw queries
   - `Model::query()` and `Marwa\DB\ORM\QueryBuilder` for ORM work
   - `Schema::init()` and schema builders for migrations and tables
4. Update or add tests for every behavior change.
5. Verify with the smallest relevant command first, then expand if needed.

## Initialization

Initialize the library before using facades or ORM models:

```php
$manager = Bootstrap::init($config);
DB::setManager($manager);
Model::setConnectionManager($manager);
Schema::init($manager);
```

## ORM guidance

- Prefer `Model::query()`, `Model::with()`, `Model::withCount()`, `Model::whereKey()`, and `Model::newQuery()` for model work.
- Use local scopes for reusable query logic.
- Use shorthand relations only when the related model lives in the same namespace as the parent model.
- Keep explicit `Related::class` relations when the related model lives elsewhere or when the relation requires extra pivot metadata.

Example:

```php
class User extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany('user_id');
    }
}
```

## Query builder guidance

- Use `when()` and `unless()` for conditional filters.
- Use `whereKey()` for primary-key lookups.
- Use `withCount()` when the query needs related counts.
- Prefer chaining over raw SQL unless the query is easier to express directly.

## Changes

- Keep methods small and typed.
- Preserve existing public APIs.
- Add regression tests for behavior changes.
- Run `phpstan` and `phpunit` before finishing substantial work.
