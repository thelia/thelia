<?php

declare(strict_types=1);

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
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Describes the Thelia database configuration.
 */
class DatabaseConfiguration implements ConfigurationInterface
{
    /**
     * Name of the main database connection used by Thelia.
     *
     * @var string
     */
    public const THELIA_CONNECTION_NAME = 'TheliaMain';

    /**
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('database');
        $databaseNode = $treeBuilder->getRootNode();
        $databaseNodeBuilder = $databaseNode->children();

        $connectionNode = $this->buildConnectionNode('connection', false);
        $databaseNodeBuilder->append($connectionNode);

        $connectionsNode = $this->buildConnectionNode('connections', true);
        $connectionsNode
            ->validate()
            ->ifTrue(
                fn ($connections): bool => !isset($connections[static::THELIA_CONNECTION_NAME])
            )
            ->thenInvalid(
                "The '".static::THELIA_CONNECTION_NAME."' connection must be defined."
            );
        $databaseNodeBuilder->append($connectionsNode);

        $databaseNode
            ->validate()
            ->ifTrue(
                fn ($database): bool => !empty($database['connection']) && !empty($database['connections'])
            )
            ->thenInvalid(
                "The 'database' node must contain either a 'connection' node or a 'connections' node, but not both."
            );

        return $treeBuilder;
    }

    /**
     * Build a configuration node describing one or more database connection.
     *
     * @param string $rootName node name
     * @param bool   $isArray  whether the node is a single connection or an array of connections
     *
     * @return ArrayNodeDefinition|NodeDefinition connection(s) node
     */
    public function buildConnectionNode($rootName, $isArray)
    {
        $treeBuilder = new TreeBuilder($rootName);
        $connectionNode = $treeBuilder->getRootNode();
        if ($isArray) {
            /** @var ArrayNodeDefinition $connectionNodePrototype */
            $connectionNodePrototype = $connectionNode->prototype('array');
            $connectionNodeBuilder = $connectionNodePrototype->children();
        } else {
            $connectionNodeBuilder = $connectionNode->children();
        }

        $connectionNodeBuilder->scalarNode('driver')
            ->defaultValue('mysql');

        $connectionNodeBuilder->scalarNode('user')
            ->defaultValue('root');

        $connectionNodeBuilder->scalarNode('password')
            ->defaultValue('');

        $connectionNodeBuilder->scalarNode('dsn')
            ->cannotBeEmpty();

        $connectionNodeBuilder->scalarNode('classname')
            ->defaultValue(ConnectionWrapper::class);

        return $connectionNode;
    }
}
