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

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class DatabaseConfiguration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root("database");

        $rootNode
            ->children()
                ->arrayNode("connection")
                    ->children()
                        ->scalarNode("driver")
                            ->defaultValue("mysql")
                        ->end()
                        ->scalarNode("user")
                            ->defaultValue("root")
                        ->end()
                        ->scalarNode("password")
                            ->defaultValue("")
                        ->end()
                        ->scalarNode("dsn")
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode("classname")
                            ->defaultValue('\Propel\Runtime\Connection\ConnectionWrapper')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
