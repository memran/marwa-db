<?php

declare(strict_types=1);

namespace Marwa\DB;

use Marwa\DB\Config\Config;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Logger\QueryLogger;
use Marwa\DB\Support\DebugBarAdapter;
use Marwa\DB\Support\DebugPanel;
use Psr\Log\LoggerInterface;


final class Bootstrap
{
    /**
     * @param array<string, array{driver:string,host?:string,port?:int,database:string,username?:string,password?:string,charset?:string,options?:array<int,int>,debug?:bool}> $dbConfig
     */
    public static function init(array $dbConfig, ?LoggerInterface $logger = null, bool $enableDebugPanel = false): ConnectionManager
    {
        $config = new Config($dbConfig);
        $manager = new ConnectionManager($config, $logger);

        if ($enableDebugPanel) {
            $manager->setDebugPanel(new DebugPanel());
            $manager->setDebugBar(DebugBarAdapter::createDefault($logger));
            $manager->setQueryLogger(new QueryLogger($logger));
        }
        $manager->setAsGlobal();
        return $manager;
    }
}
