<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use Marwa\DB\ORM\Relations\BelongsToMany;

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
Model::setConnectionManager($cm, 'sqlite');

$pdo = $cm->getPdo('sqlite');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
$pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
$pdo->exec('CREATE TABLE role_user (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL, granted_at TEXT NULL)');

final class User extends Model
{
    protected static ?string $table = 'users';

    protected static array $fillable = ['name'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }
}

final class Role extends Model
{
    protected static ?string $table = 'roles';

    protected static array $fillable = ['name'];
}

$user = User::create(['name' => 'Alice']);
$admin = Role::create(['name' => 'Admin']);
$editor = Role::create(['name' => 'Editor']);

$relation = $user->roles();
$relation->attach($user, [
    $admin->getKey() => ['granted_at' => date('Y-m-d H:i:s')],
    $editor->getKey() => ['granted_at' => date('Y-m-d H:i:s')],
]);

$loaded = User::with('roles')->first();
if ($loaded !== null) {
    echo $loaded->getAttribute('name') . " roles:\n";

    foreach ($loaded->getRelationValue('roles') as $role) {
        $pivot = $role->getRelation('pivot');
        printf(
            "- %s (granted_at: %s)\n",
            $role->getAttribute('name'),
            $pivot['granted_at'] ?? 'n/a'
        );
    }
}
