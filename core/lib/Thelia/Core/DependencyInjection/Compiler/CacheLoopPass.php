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

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Class CacheLoopPass
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author Manuel Raynaud <manu@thelia.net>
 */
class CacheLoopPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (false === $container->has('thelia.cache.loop')) {
            $container->addDefinitions([
                'thelia.cache.loop' => new Definition("Thelia\\Cache\\Loop\\ArrayCacheLoop")
            ]);
        } else {
            $class = $container->getDefinition('thelia.cache.loop')->getClass();

            if (false === is_subclass_of($class, "Thelia\\Cache\\Loop\\CacheLoopInterface")) {
                throw new \InvalidArgumentException('Service "thelia.cache" should implement the Thelia\Cache\Loop\CacheLoopInterface');
            }
        }
    }
}
