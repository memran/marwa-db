<?php

declare(strict_types=1);

namespace Marwa\DB;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Support\DebugPanel;
use Psr\Log\LoggerInterface;

final class Bootstrap
{
    public static function init(array $dbConfig, ?LoggerInterface $logger = null, bool $enableDebugPanel = false): ConnectionManager
    {
        $config = new Config($dbConfig);
        $manager = new ConnectionManager($config, $logger);

        if ($enableDebugPanel) {
            $manager->setDebugPanel(new DebugPanel());
        }

        return $manager;
    }
}
