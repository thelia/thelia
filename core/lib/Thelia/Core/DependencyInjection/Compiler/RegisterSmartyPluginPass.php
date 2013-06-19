<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 18/06/13
 * Time: 21:55
 * To change this template use File | Settings | File Templates.
 */

namespace Thelia\Core\DependencyInjection\Compiler;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RegisterSmartyPluginPass implements CompilerPassInterface {

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

        foreach ($container->findTaggedServiceIds("smarty.register_plugin") as $id => $plugin) {

            $smarty->addMethodCall("addPlugins", array(new Reference($id)));

        }

        $smarty->addMethodCall("registerPlugins");
    }
}