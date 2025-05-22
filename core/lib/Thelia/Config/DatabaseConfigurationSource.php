<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Config;

use Propel\Runtime\Connection\ConnectionWrapper;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Processes the Thelia database configuration.
 *
 * @todo Also read the database configuration file here ?
 */
class DatabaseConfigurationSource
{
    /**
     * Map of [connection name => connection ParameterBag].
     *
     * @var array
     */
    protected $connections;

    /**
     * @param array $envParameters environment parameters
     */
    public function __construct(
        array $envParameters,
    ) {
        $this->configureConnections($envParameters);
    }

    protected function configureConnections($envParameters): void
    {
        if (null !== $envParameters['thelia.database_host'] && null !== $envParameters['thelia.database_name']) {
            $this->addConnection(
                DatabaseConfiguration::THELIA_CONNECTION_NAME,
                [
                    'driver' => 'mysql',
                    'user' => $envParameters['thelia.database_user'],
                    'password' => $envParameters['thelia.database_password'],
                    'dsn' => sprintf('mysql:host=%s;dbname=%s;port=%s', $envParameters['thelia.database_host'], $envParameters['thelia.database_name'], $envParameters['thelia.database_port']),
                    'classname' => ConnectionWrapper::class,
                ],
                $envParameters
            );

            return;
        }

        $fs = new Filesystem();

        $databaseConfigFile = THELIA_CONF_DIR.'database_'.$envParameters['kernel.environment'].'.yml';
        if (!$fs->exists($databaseConfigFile)) {
            $databaseConfigFile = THELIA_CONF_DIR.'database.yml';
        }

        if (!$fs->exists($databaseConfigFile)) {
            throw new \LogicException('No database connection found. Add parameters to your .env file or a database.yml');
        }

        $theliaDatabaseConfiguration = Yaml::parse(file_get_contents($databaseConfigFile));

        $configurationProcessor = new Processor();
        $configuration = $configurationProcessor->processConfiguration(
            new DatabaseConfiguration(),
            $theliaDatabaseConfiguration
        );

        // single connection format
        if (isset($configuration['connection'])) {
            $this->addConnection(
                DatabaseConfiguration::THELIA_CONNECTION_NAME,
                $configuration['connection'],
                $envParameters
            );

            return;
        }

        // multiple connections format
        if (isset($configuration['connections'])) {
            foreach ($configuration['connections'] as $connectionName => $connectionParameters) {
                $this->addConnection(
                    $connectionName,
                    $connectionParameters,
                    $envParameters
                );
            }

            return;
        }

        throw new \LogicException(
            'Connection configuration not found'
                .' This is checked at configuration validation, and should not happen.'
        );
    }

    /**
     * Create and resolve a ParameterBag for a connection.
     * Add the bag to the connections map.
     *
     * @param string $name          connection name
     * @param array  $parameters    connection parameters
     * @param array  $envParameters environment parameters
     */
    protected function addConnection($name, array $parameters = [], array $envParameters = []): void
    {
        $connectionParameterBag = new ParameterBag($envParameters);
        $connectionParameterBag->add($parameters);
        $this->connections[$name] = $connectionParameterBag;
    }

    /**
     * @return array propel configuration values
     */
    public function getPropelConnectionsConfiguration()
    {
        $propelConnections = [];
        /**
         * @var string       $connectionName
         * @var ParameterBag $connectionParameterBag
         */
        foreach ($this->connections as $connectionName => $connectionParameterBag) {
            $propelConnections[$connectionName] = [
                'adapter' => $connectionParameterBag->get('driver'),
                'dsn' => $connectionParameterBag->get('dsn'),
                'user' => $connectionParameterBag->get('user'),
                'password' => $connectionParameterBag->get('password'),
                'classname' => $connectionParameterBag->get('classname'),
                'settings' => [
                    'queries' => [
                        "SET NAMES 'UTF8'",
                    ],
                ],
            ];
        }

        $propelConfiguration = [];
        $propelConfiguration['propel']['database']['connections'] = $propelConnections;
        $propelConfiguration['propel']['runtime']['defaultConnection'] = DatabaseConfiguration::THELIA_CONNECTION_NAME;

        return $propelConfiguration;
    }

    /**
     * @throws \PDOException
     *
     * @return \PDO thelia database connection
     */
    public function getTheliaConnectionPDO()
    {
        /** @var ParameterBag $theliaConnectionParameterBag */
        $theliaConnectionParameterBag = $this->connections[DatabaseConfiguration::THELIA_CONNECTION_NAME];

        return new \PDO(
            $theliaConnectionParameterBag->get('dsn'),
            $theliaConnectionParameterBag->get('user'),
            $theliaConnectionParameterBag->get('password'),
            [
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
            ]
        );
    }
}
