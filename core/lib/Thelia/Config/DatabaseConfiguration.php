<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
                            ->validate()
                            ->ifNotInArray(array("mysql", "sqlite", "pgsql"))
                                ->thenInvalid("Invalid driver database %s")
                            ->end()
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
                            ->defaultValue("\Propel\Runtime\Connection\ConnectionWrapper")
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
