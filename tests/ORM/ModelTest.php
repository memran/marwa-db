<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\ORM;

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
