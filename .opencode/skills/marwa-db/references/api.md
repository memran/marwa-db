# API Surface

## Bootstrapping

- `Bootstrap::init(array $config, ?LoggerInterface $logger = null, bool $enableDebugPanel = false): ConnectionManager`
- `DB::setManager(ConnectionManager $manager): void`
- `Model::setConnectionManager(ConnectionManager $manager, string $connection = 'default'): void`
- `Schema::init(ConnectionManager $manager): void`

## Database

- `DB::connection(?string $name = null): ConnectionManager`
- `DB::table(string $table, string $conn = 'default'): Builder`
- `DB::transaction(callable $callback, string $conn = 'default'): mixed`

## Query builder

Use `Marwa\DB\Query\Builder` for raw queries.

- `select(...)`, `where(...)`, `when(...)`, `unless(...)`
- `whereKey(...)`, `whereIn(...)`, `whereJsonContains(...)`
- `groupBy(...)`, `having(...)`, `orderBy(...)`
- `get()`, `first()`, `paginate(...)`, `chunk(...)`, `chunkById(...)`
- `insert(...)`, `update(...)`, `delete()`
- `withCount(...)`, `toSql()`, `getBindings()`

## ORM

Use `Marwa\DB\ORM\Model` for Active Record models.

- `query()`, `newQuery()`, `with(...)`, `withCount(...)`
- `where(...)`, `whereKey(...)`, `find(...)`, `first()`
- `create(...)`, `save()`, `updateOrCreate(...)`, `firstOrCreate(...)`
- `withTrashed()`, `onlyTrashed()`, `refresh()`, `toArray()`

## Relations

- `hasOne('foreign_key')`, `hasMany('foreign_key')`, `belongsTo('foreign_key')`
- Keep `Related::class` explicit when the related model is not in the same namespace or when pivot metadata is required.
- `belongsToMany(Related::class, 'pivot_table', 'foreign_pivot_key', 'related_pivot_key', 'parent_key', 'related_key', ['pivot_columns'])`

## Schema and seeding

- `Schema::create(...)`, `Schema::table(...)`, `Schema::drop(...)`
- `SeedRunner` and `AbstractSeeder`
- CLI commands in `src/CLI/Commands/`
