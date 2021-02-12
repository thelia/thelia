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

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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
     * @param array $theliaDatabaseConfiguration thelia database configuration
     * @param array $envParameters               environment parameters
     */
    public function __construct(
        array $theliaDatabaseConfiguration,
        array $envParameters
    ) {
        $configurationProcessor = new Processor();
        $configuration = $configurationProcessor->processConfiguration(
            new DatabaseConfiguration(),
            $theliaDatabaseConfiguration
        );

        if (isset($configuration['connection'])) {
            // single connection format
            $this->addConnection(
                DatabaseConfiguration::THELIA_CONNECTION_NAME,
                $configuration['connection'],
                $envParameters
            );
        } elseif (isset($configuration['connections'])) {
            // multiple connections format
            foreach ($configuration['connections'] as $connectionName => $connectionParameters) {
                $this->addConnection(
                    $connectionName,
                    $connectionParameters,
                    $envParameters
                );
            }
        } else {
            throw new \LogicException(
                "No 'connection' or 'connections' node under the 'database' node."
                .' This is checked at configuration validation, and should not happen.'
            );
        }

        if (!isset($this->connections[DatabaseConfiguration::THELIA_CONNECTION_NAME])) {
            throw new \LogicException(
                "Connection '".DatabaseConfiguration::THELIA_CONNECTION_NAME."' is not defined."
                .' This is checked at configuration validation, and should not happen.'
            );
        }
    }

    /**
     * Create and resolve a ParameterBag for a connection.
     * Add the bag to the connections map.
     *
     * @param string $name          connection name
     * @param array  $parameters    connection parameters
     * @param array  $envParameters environment parameters
     */
    protected function addConnection($name, array $parameters = [], array $envParameters = [])
    {
        $connectionParameterBag = new ParameterBag($envParameters);
        $connectionParameterBag->add($parameters);
        $connectionParameterBag->resolve();
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
     * @return \PDO thelia database connection
     *
     * @throws \PDOException
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
