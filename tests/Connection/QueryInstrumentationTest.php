<?php

declare(strict_types=1);

namespace Marwa\DB\Tests\Connection;

use Marwa\DB\Config\Config;
use Marwa\DB\Bootstrap;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\ORM\Model;
use Marwa\DB\Query\Builder;
use Marwa\DB\Support\DebugPanel;
use function Marwa\DB\Support\db_debugbar;
use PDOException;
use PHPUnit\Framework\TestCase;

final class QueryInstrumentationTest extends TestCase
{
    protected function tearDown(): void
    {
        InstrumentedOrmUser::setConnectionManager($this->makeManager(false));
    }

    public function testQueryBuilderQueryIsLoggedOnce(): void
    {
        $manager = $this->makeManager();
        $pdo = $manager->getPdo();
        $panel = $manager->getDebugPanel();

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $panel?->clear();

        $rows = (new Builder($manager))
            ->table('users')
            ->where('name', '=', 'Alice')
            ->get();

        $entries = $panel?->all() ?? [];

        self::assertCount(1, $entries);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame(['Alice'], $entries[0]['bindings']);
    }

    public function testOrmModelQueryIsLoggedOnce(): void
    {
        $manager = $this->makeManager();
        $pdo = $manager->getPdo();
        $panel = $manager->getDebugPanel();

        $pdo->exec('CREATE TABLE orm_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec("INSERT INTO orm_users (name) VALUES ('Alice')");
        $panel?->clear();

        InstrumentedOrmUser::setConnectionManager($manager);
        $user = InstrumentedOrmUser::query()->where('name', '=', 'Alice')->first();

        self::assertCount(1, $panel?->all() ?? []);
        self::assertSame('Alice', $user?->getAttribute('name'));
    }

    public function testRawPdoQueryIsLogged(): void
    {
        $manager = $this->makeManager();
        $pdo = $manager->getPdo();
        $panel = $manager->getDebugPanel();

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $panel?->clear();

        $rows = $pdo->query('SELECT * FROM users')->fetchAll();
        $entries = $panel?->all() ?? [];

        self::assertCount(1, $entries);
        self::assertSame([], $entries[0]['bindings']);
        self::assertSame('Alice', $rows[0]['name']);
    }

    public function testRawPreparedStatementExecutionIsLoggedWithBindings(): void
    {
        $manager = $this->makeManager();
        $pdo = $manager->getPdo();
        $panel = $manager->getDebugPanel();

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $panel?->clear();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE name = ?');
        $stmt->execute(['Alice']);

        $entries = $panel?->all() ?? [];

        self::assertCount(1, $entries);
        self::assertSame('SELECT * FROM users WHERE name = ?', $entries[0]['sql']);
        self::assertSame(['Alice'], $entries[0]['bindings']);
        self::assertNull($entries[0]['error']);
    }

    public function testRawExecIsLogged(): void
    {
        $manager = $this->makeManager();
        $panel = $manager->getDebugPanel();

        $manager->getPdo()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $panel?->clear();

        $affected = $manager->getPdo()->exec("INSERT INTO users (name) VALUES ('Alice')");
        $entries = $panel?->all() ?? [];

        self::assertSame(1, $affected);
        self::assertCount(1, $entries);
        self::assertSame("INSERT INTO users (name) VALUES ('Alice')", $entries[0]['sql']);
    }

    public function testFailedPreparedStatementPreservesExceptionAndLogsContext(): void
    {
        $manager = $this->makeManager();
        $panel = $manager->getDebugPanel();
        $pdo = $manager->getPdo();

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE)');
        $pdo->exec("INSERT INTO users (email) VALUES ('alice@example.com')");
        $panel?->clear();

        try {
            $stmt = $pdo->prepare('INSERT INTO users (email) VALUES (?)');
            $stmt->execute(['alice@example.com']);
            self::fail('Expected PDOException was not thrown.');
        } catch (PDOException $e) {
            $entries = $panel?->all() ?? [];

            self::assertCount(1, $entries);
            self::assertSame('INSERT INTO users (email) VALUES (?)', $entries[0]['sql']);
            self::assertSame(['alice@example.com'], $entries[0]['bindings']);
            self::assertNotNull($entries[0]['error']);
            self::assertStringContainsString('UNIQUE', strtoupper($entries[0]['error'] ?? ''));
            self::assertInstanceOf(PDOException::class, $e);
        }
    }

    public function testLoggingIsDisabledWhenDebugIsOff(): void
    {
        $manager = $this->makeManager(false);
        $pdo = $manager->getPdo();

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->query('SELECT * FROM users');

        self::assertNull($manager->getDebugPanel());
        self::assertNull($manager->getQueryLogger());
    }

    public function testInstalledDebugBarReceivesLoggedQueries(): void
    {
        $manager = Bootstrap::init([
            'default' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ], enableDebugPanel: true);

        $pdo = $manager->getPdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $manager->getDebugPanel()?->clear();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE name = ?');
        $stmt->execute(['Alice']);

        $debugBar = $manager->getDebugBar();
        self::assertIsObject($debugBar);
        self::assertTrue(method_exists($debugBar, 'state'));

        $state = $debugBar->state();
        self::assertCount(3, $state->queries);
        self::assertSame('SELECT * FROM users WHERE name = ?', $state->queries[2]['sql']);
        self::assertSame(['Alice'], $state->queries[2]['params']);
    }

    public function testConnectionManagerCanRenderInstalledDebugBar(): void
    {
        $manager = Bootstrap::init([
            'default' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ], enableDebugPanel: true);

        $pdo = $manager->getPdo();
        $pdo->query('SELECT 1');

        $html = $manager->renderDebugBar();

        self::assertStringContainsString('Queries', $html);
        self::assertStringContainsString('SELECT 1', $html);
    }

    public function testGlobalDebugBarHelperRendersFromGlobalConnectionManager(): void
    {
        $manager = Bootstrap::init([
            'default' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ], enableDebugPanel: true);

        $manager->getPdo()->query('SELECT 1');

        $html = db_debugbar();

        self::assertStringContainsString('Queries', $html);
        self::assertStringContainsString('SELECT 1', $html);
    }

    private function makeManager(bool $withDebugPanel = true): ConnectionManager
    {
        $manager = new ConnectionManager(new Config([
            'default' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]));

        if ($withDebugPanel) {
            $manager->setDebugPanel(new DebugPanel());
        }

        return $manager;
    }
}

final class InstrumentedOrmUser extends Model
{
    protected static ?string $table = 'orm_users';
}
