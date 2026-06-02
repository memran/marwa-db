# Examples

## Query builder

```php
$users = DB::table('users')
    ->when($onlyActive, fn ($q) => $q->where('active', '=', 1))
    ->whereKey([1, 2, 3])
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get();
```

## ORM query

```php
$users = User::query()
    ->with('posts')
    ->withCount('posts')
    ->where('active', '=', 1)
    ->get();
```

## Local scope

```php
class User extends Model
{
    public function scopeActive($query): void
    {
        $query->where('active', '=', 1);
    }
}

$users = User::active()->orderBy('name')->get();
```

## Relations

```php
class User extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany('user_id');
    }
}
```

## Soft deletes

```php
$all = Post::withTrashed()->get();
$trashed = Post::onlyTrashed()->get();
```
