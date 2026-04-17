<?php

declare(strict_types=1);

namespace Marwa\DB\Connection;

final class ConnectionFactory
{
    private const ALLOWED_DRIVERS = ['mysql', 'pgsql', 'sqlite'];

    public function makePdo(array $config, ?callable $recorder = null, string $connectionName = 'default'): \PDO
    {
        $driver = $config['driver'] ?? 'mysql';
        $options = $config['options'] ?? [];

        if (!in_array($driver, self::ALLOWED_DRIVERS, true)) {
            throw new \InvalidArgumentException("Unsupported driver: {$driver}");
        }

        $dsn = match ($driver) {
            'mysql' => $this->mysqlDsn($config),
            'pgsql' => $this->pgsqlDsn($config),
            'sqlite' => $this->sqliteDsn($config),
        };

        $pdo = new InstrumentedPdo(
            $dsn,
            $config['username'] ?? '',
            $config['password'] ?? '',
            $options + [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
            $recorder,
            $connectionName
        );

        return $pdo;
    }

    private function mysqlDsn(array $config): string
    {
        $host = trim((string) ($config['host'] ?? '127.0.0.1'));
        $port = (int) ($config['port'] ?? 3306);
        $dbname = trim((string) ($config['database'] ?? ''));
        $charset = trim((string) ($config['charset'] ?? 'utf8mb4'));

        if ($dbname === '') {
            throw new \InvalidArgumentException('MySQL connections require a database name.');
        }

        return "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
    }

    private function pgsqlDsn(array $config): string
    {
        $host = trim((string) ($config['host'] ?? '127.0.0.1'));
        $port = (int) ($config['port'] ?? 5432);
        $dbname = trim((string) ($config['database'] ?? ''));
        $charset = trim((string) ($config['charset'] ?? 'utf8'));

        if ($dbname === '') {
            throw new \InvalidArgumentException('PostgreSQL connections require a database name.');
        }

        return "pgsql:host={$host};port={$port};dbname={$dbname};options='--client_encoding={$charset}'";
    }

    private function sqliteDsn(array $config): string
    {
        $database = trim((string) ($config['database'] ?? ''));
        if ($database === '') {
            throw new \InvalidArgumentException('SQLite connections require a database path or :memory:.');
        }

        return 'sqlite:' . $database;
    }
}
