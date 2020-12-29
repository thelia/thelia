<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Config;

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
     * @var string
     */
    const THELIA_CONNECTION_NAME = 'TheliaMain';

    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder()
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
                function ($connections) {
                    return !isset($connections[static::THELIA_CONNECTION_NAME]);
                }
            )
            ->thenInvalid(
                "The '" . static::THELIA_CONNECTION_NAME . "' connection must be defined."
            );
        $databaseNodeBuilder->append($connectionsNode);

        $databaseNode
            ->validate()
            ->ifTrue(
                function ($database) {
                    return !empty($database['connection']) && !empty($database['connections']);
                }
            )
            ->thenInvalid(
                "The 'database' node must contain either a 'connection' node or a 'connections' node, but not both."
            );

        return $treeBuilder;
    }

    /**
     * Build a configuration node describing one or more database connection
     * @param string $rootName Node name.
     * @param bool $isArray Whether the node is a single connection or an array of connections.
     * @return ArrayNodeDefinition|NodeDefinition Connection(s) node.
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
            ->defaultValue('\Propel\Runtime\Connection\ConnectionWrapper');

        return $connectionNode;
    }
}
