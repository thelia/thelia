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

namespace TheliaSmarty\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Register parser plugins. These plugins shouild be tagged thelia.parser.register_plugin
 * in the configuration.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class RegisterParserPluginPass implements CompilerPassInterface
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
        if (!$container->hasDefinition("thelia.parser")) {
            return;
        }

        $smarty = $container->getDefinition("thelia.parser");

        foreach ($container->findTaggedServiceIds("thelia.parser.register_plugin") as $id => $plugin) {
            $smarty->addMethodCall("addPlugins", array(new Reference($id)));
        }

        $smarty->addMethodCall("registerPlugins");
    }
}
