<?php

declare(strict_types=1);

namespace Marwa\DB\Connection;

use Marwa\DB\Config\Config;
use Marwa\DB\Support\DebugPanel;
use Psr\Log\LoggerInterface;
use Closure;
use Throwable;

final class ConnectionManager implements ConnectionInterface
{
    /** @var array<string,\PDO> */
    private array $pool = [];

    private ?DebugPanel $debugPanel = null;

    public function __construct(
        private Config $config,
        private ?LoggerInterface $logger = null,
        private RetryPolicy $retry = new RetryPolicy(),
        private LoadBalancer $lb = new LoadBalancer(),
        private ConnectionFactory $factory = new ConnectionFactory()
    ) {}

    public function setDebugPanel(?DebugPanel $panel): void
    {
        $this->debugPanel = $panel;
    }

    public function getDebugPanel(): ?DebugPanel
    {
        return $this->debugPanel;
    }

    /**
     * Run a set of queries inside a database transaction.
     *
     * @template T
     * @param Closure $callback The callback to run in the transaction. The PDO instance will be passed as the first argument.
     * @param string|null $connectionName Optional connection name, defaults to the default connection.
     * @return mixed The return value of the callback.
     * @throws Throwable If an exception occurs, the transaction will be rolled back and rethrown.
     */
    public function transaction(Closure $callback, ?string $connectionName = null)
    {
        $connection = $this->getPdo($connectionName);

        try {
            $connection->beginTransaction();

            // Execute callback with PDO instance
            $result = $callback($connection);

            $connection->commit();
            return $result;
        } catch (Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }

    public function getPdo(string $name = 'default'): \PDO
    {
        if (!isset($this->pool[$name])) {
            $this->pool[$name] = $this->connectWithRetry($name);
        }
        return $this->pool[$name];
    }

    public function getConnection(string $name = 'default'): \PDO
    {
        return $this->getPdo($name);
    }

    private function connectWithRetry(string $name): \PDO
    {
        $cfg = $this->config->get($name);
        if (!$cfg) {
            throw new \InvalidArgumentException("Connection [$name] not configured.");
        }

        $attempt = 0;
        $delay = $this->retry->delayMs();

        while (true) {
            try {
                return $this->factory->makePdo($cfg);
            } catch (\Throwable $e) {
                $attempt++;
                $this->logger?->warning("[DB] connect failed ({$name}) attempt {$attempt}: " . $e->getMessage());

                if ($attempt >= $this->retry->attempts()) {
                    throw $e;
                }

                usleep($delay * 1000);
                if ($this->retry->exponentialBackoff()) {
                    $delay *= 2;
                }
            }
        }
    }

    public function isDebug(string $name = 'default'): bool
    {
        $cfg = $this->config->get($name);
        return (bool)($cfg['debug'] ?? false);
    }

    /**
     * @param array<string> $replicas
     */
    public function pickReplica(array $replicas): \PDO
    {
        $chosen = $this->lb->pick($replicas);
        return $this->getPdo($chosen);
    }
}
