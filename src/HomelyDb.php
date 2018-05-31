<?php
namespace HomelyDb;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

class HomelyDb
{
    /** @var \Doctrine\DBAL\Connection */
    private static $connection;

    private static $instance;

    /**
     * @param array $params
     * @return HomelyDb
     */
    public static function init($params)
    {
        if (null === self::$instance) {
            self::$instance = new static($params);
        }

        return self::$instance;
    }

    /** @return \Doctrine\DBAL\Connection */
    public static function getConnection()
    {
        return self::$connection;
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }

    protected function __construct($params)
    {
        self::$connection = DriverManager::getConnection($params, new Configuration());
    }
}
