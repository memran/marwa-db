<?php

declare(strict_types=1);

namespace Marwa\DB\Connection;

use Marwa\DB\Config\Config;
use Marwa\DB\Logger\QueryLogger;
use Marwa\DB\Support\DebugBarAdapter;
use Marwa\DB\Support\DebugPanel;
use Psr\Log\LoggerInterface;
use Closure;
use Throwable;

final class ConnectionManager implements ConnectionInterface
{
    /** @var array<string,\PDO> */
    private array $pool = [];

    private ?DebugPanel $debugPanel = null;
    private ?QueryLogger $queryLogger = null;
    private ?object $debugBar = null;

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

    public function setDebugBar(?object $debugBar): void
    {
        $this->debugBar = DebugBarAdapter::supports($debugBar) ? $debugBar : null;
    }

    public function getDebugBar(): ?object
    {
        return $this->debugBar;
    }

    public function renderDebugBar(): string
    {
        return DebugBarAdapter::render($this->debugBar);
    }

    public function setQueryLogger(?QueryLogger $queryLogger): void
    {
        $this->queryLogger = $queryLogger;
    }

    public function getQueryLogger(): ?QueryLogger
    {
        return $this->queryLogger;
    }

    /**
     * Run a set of queries inside a database transaction.
     *
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

    public function getPdo(?string $name = 'default'): \PDO
    {
        $name = $name ?: 'default';

        if (!isset($this->pool[$name])) {
            $this->pool[$name] = $this->connectWithRetry($name);
        }
        return $this->pool[$name];
    }

    public function getConnection(?string $name = 'default'): \PDO
    {
        return $this->getPdo($name);
    }

    public function setAsGlobal()
    {
        $GLOBALS['cm'] = $this;
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
                return $this->factory->makePdo($cfg, $this->recordQuery(...), $name);
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

    public function getDriver(string $name = 'default'): string
    {
        $cfg = $this->config->get($name);

        return (string)($cfg['driver'] ?? 'mysql');
    }

    /**
     * @param array<string> $replicas
     */
    public function pickReplica(array $replicas): \PDO
    {
        $chosen = $this->lb->pick($replicas);
        return $this->getPdo($chosen);
    }

    public function recordQuery(
        string $sql,
        array $bindings,
        float $timeMs,
        string $connection,
        ?string $error = null
    ): void {
        if (!$this->shouldRecordQuery($connection)) {
            return;
        }

        $this->debugPanel?->addQuery($sql, $bindings, $timeMs, $connection, $error);
        DebugBarAdapter::addQuery($this->debugBar, $sql, $bindings, $timeMs, $connection);
        $this->resolveQueryLogger($connection)?->log($sql, $bindings, $timeMs, $connection, $error);
    }

    private function shouldRecordQuery(string $connection): bool
    {
        return $this->debugPanel !== null
            || $this->debugBar !== null
            || $this->queryLogger !== null
            || $this->isDebug($connection);
    }

    private function resolveQueryLogger(string $connection): ?QueryLogger
    {
        if ($this->queryLogger !== null) {
            return $this->queryLogger;
        }

        if (!$this->isDebug($connection)) {
            return null;
        }

        $this->queryLogger = new QueryLogger($this->logger);

        return $this->queryLogger;
    }
}
