<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\ORM;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testCreateHonorsFillableAttributes(): void
    {
        $model = FillableUser::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'role' => 'admin',
        ]);

        self::assertSame('Alice', $model->getAttribute('name'));
        self::assertSame('alice@example.com', $model->getAttribute('email'));
        self::assertNull($model->getAttribute('role'));
    }

    public function testCreateReturnsEmptyAttributesWhenGuardedWildcardIsActive(): void
    {
        $model = GuardedUser::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

        self::assertNull($model->getAttribute('name'));
        self::assertNull($model->getAttribute('email'));
    }

    public function testDestroyAcceptsScalarPrimaryKey(): void
    {
        $manager = new ConnectionManager(new Config([
            'default' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]));
        $pdo = $manager->getPdo();
        $pdo->exec('CREATE TABLE destroy_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec("INSERT INTO destroy_users (name) VALUES ('Alice')");
        $pdo->exec("INSERT INTO destroy_users (name) VALUES ('Bob')");

        DestroyUser::setConnectionManager($manager);

        $deleted = DestroyUser::destroy(1);

        self::assertSame(1, $deleted);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM destroy_users')->fetchColumn());
    }
}

final class FillableUser extends Model
{
    protected static array $fillable = ['name', 'email'];

    public static function create(array $attributes): static
    {
        return new static(static::filterFillable($attributes), true);
    }
}

final class GuardedUser extends Model
{
    public static function create(array $attributes): static
    {
        return new static(static::filterFillable($attributes), true);
    }
}

final class DestroyUser extends Model
{
    protected static ?string $table = 'destroy_users';
}
