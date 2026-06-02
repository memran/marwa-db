<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\ORM;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use PHPUnit\Framework\TestCase;

final class ScopeTest extends TestCase
{
    public function testLocalScopesChainThroughTheModelShortcut(): void
    {
        $manager = $this->makeManager();
        $this->seedUsers($manager);

        ScopedUser::setConnectionManager($manager);

        $user = ScopedUser::active()->where('name', '=', 'Alice')->first();

        self::assertInstanceOf(ScopedUser::class, $user);
        self::assertSame('Alice', $user?->getAttribute('name'));
    }

    public function testLocalScopesChainThroughTheQueryBuilder(): void
    {
        $manager = $this->makeManager();
        $this->seedUsers($manager);

        ScopedUser::setConnectionManager($manager);

        $users = ScopedUser::query()
            ->active()
            ->popular()
            ->orderBy('name')
            ->get();

        self::assertCount(1, $users);
        self::assertSame(['Bob'], array_map(
            static fn (ScopedUser $user): string => (string) $user->getAttribute('name'),
            $users
        ));
    }

    public function testLocalScopesChainThroughTheInstanceShortcut(): void
    {
        $manager = $this->makeManager();
        $this->seedUsers($manager);

        ScopedUser::setConnectionManager($manager);

        $user = (new ScopedUser([], true))
            ->active()
            ->where('name', '=', 'Bob')
            ->first();

        self::assertInstanceOf(ScopedUser::class, $user);
        self::assertSame('Bob', $user?->getAttribute('name'));
    }

    private function makeManager(): ConnectionManager
    {
        return new ConnectionManager(new Config([
            'default' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]));
    }

    private function seedUsers(ConnectionManager $manager): void
    {
        $pdo = $manager->getPdo();
        $pdo->exec(
            'CREATE TABLE scoped_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                active INTEGER NOT NULL,
                votes INTEGER NOT NULL
            )'
        );
        $pdo->exec("INSERT INTO scoped_users (name, active, votes) VALUES ('Alice', 1, 5)");
        $pdo->exec("INSERT INTO scoped_users (name, active, votes) VALUES ('Bob', 1, 20)");
        $pdo->exec("INSERT INTO scoped_users (name, active, votes) VALUES ('Carol', 0, 30)");
    }
}

final class ScopedUser extends Model
{
    protected static ?string $table = 'scoped_users';

    public function scopeActive($query): void
    {
        $query->where('active', '=', 1);
    }

    public function scopePopular($query): void
    {
        $query->where('votes', '>', 10);
    }
}
