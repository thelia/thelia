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

/**
 * Class StackPass
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class StackPass implements CompilerPassInterface
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
        if (!$container->hasDefinition('stack_factory')) {
            return;
        }

        $stackFactory = $container->getDefinition('stack_factory');

        $stackPriority = [];

        foreach ($container->findTaggedServiceIds('stack_middleware') as $id => $attributes) {
            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;

            $definition = $container->getDefinition($id);
            $arguments = $definition->getArguments();
            array_unshift($arguments, $definition->getClass());

            $stackPriority[$priority][] = $arguments;
        }

        if (false === empty($stackPriority)) {
            krsort($stackPriority);

            foreach ($stackPriority as $priority => $stacks) {
                foreach ($stacks as $arguments) {
                    $stackFactory->addMethodCall('push', $arguments);
                }
            }
        }
    }
}
