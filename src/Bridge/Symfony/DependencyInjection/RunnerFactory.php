<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection;

use Doctrine\DBAL\Connection;
use Goat\Converter\ConverterInterface;
use Goat\Driver\Configuration;
use Goat\Driver\Driver;
use Goat\Driver\ExtPgSQLDriver;
use Goat\Driver\PDODriver;
use Goat\Runner\Runner;
use Goat\Runner\Driver\PDOMySQLRunner;
use Goat\Runner\Driver\PDOPgSQLRunner;

final class RunnerFactory
{
    /**
     * Create runner instance from Doctrine connection
     *
     * @throws \InvalidArgumentException
     *   If the underlaying doctrine platform is unsupported
     */
    public static function createFromDoctrineConnection(Connection $connection, ConverterInterface $converter): Runner
    {
        $realConnection = $connection->getWrappedConnection();
        if (!$realConnection instanceof \PDO) {
            throw new \InvalidArgumentException("Doctrine connection does not use PDO, goat database runner cannot work on top of it");
        }

        $runner = null;

        switch ($platformName = $connection->getDatabasePlatform()->getName()) {

            case 'postgresql':
                $runner = new PDOPgSQLRunner($realConnection);
                break;

            case 'mysql':
                $runner = new PDOMySQLRunner($realConnection);
                break;

            default:
                throw new \InvalidArgumentException(\sprintf(
                    "'%s' Doctrine platform is unsupported, only 'postgresql' and 'mysql' are supported",
                    $platformName
                ));
        }

        $runner->setConverter($converter);

        return $runner;
    }

    /**
     * Create connection from URI/URL/DSN
     */
    public static function createDriverFromUri(string $uri): Driver
    {
        $configuration = Configuration::fromString($uri);

        switch ($configuration->getDriver()) {

            case Configuration::DRIVER_DEFAULT_MYSQL:
                $driver = new PDODriver();
                break;

            case Configuration::DRIVER_DEFAULT_PGSQL:
                if (\function_exists('pg_connect')) {
                    $driver = new ExtPgSQLDriver();
                } else {
                    $driver = new PDODriver();
                }
                break;

            case Configuration::DRIVER_EXT_PGSQL:
                $driver = new ExtPgSQLDriver();
                break;

            case Configuration::DRIVER_PDO_MYSQL:
                $driver = new PDODriver();
                break;

            case Configuration::DRIVER_PDO_PGSQL:
                $driver = new PDODriver();
                break;
        }

        $driver->setConfiguration($configuration);

        return $driver;
    }

    /**
     * Create connection from URI/URL/DSN
     */
    public static function createRunnerFromUri(string $uri): Runner
    {
        return self::createDriverFromUri($uri)->getRunner();
    }
}
