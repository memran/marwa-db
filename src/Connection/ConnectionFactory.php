<?php

declare(strict_types=1);

namespace Marwa\DB\Connection;

final class ConnectionFactory
{
    public function makePdo(array $config): \PDO
    {
        $driver   = $config['driver']   ?? 'mysql';
        $host     = $config['host']     ?? '127.0.0.1';
        $port     = (int)($config['port'] ?? 3306);
        $dbname   = $config['database'] ?? '';
        $charset  = $config['charset']  ?? 'utf8mb4';
        $options  = $config['options']  ?? [];

        $dsn = match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}",
            default => throw new \InvalidArgumentException("Unsupported driver: {$driver}"),
        };

        $pdo = new \PDO(
            $dsn,
            $config['username'] ?? '',
            $config['password'] ?? '',
            $options + [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $pdo;
    }
}
