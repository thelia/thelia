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
        if (!$container->hasDefinition("smarty")) {
            return;
        }

        $smarty = $container->getDefinition("smarty");

        foreach ($container->findTaggedServiceIds("smarty.register_plugin") as $id => $plugin) {

            $smarty->addMethodCall("addPlugins", array(new Reference($id)));

            /*$register_plugin = $container->get($id);

            $reflectionObject = new \ReflectionObject($register_plugin);
            $interface = "Thelia\Core\Template\Smarty\SmartyPluginInterface";
            if (!$reflectionObject->implementsInterface($interface)) {
                throw new \RuntimeException(sprintf("%s class must implement %s interface",$reflectionObject->getName(), $interface));
            }

            $plugins = $register_plugin->registerPlugins();

            if(!is_array($plugins)) {
                $plugins = array($plugins);
            }

            foreach($plugins as $plugin) {
                $smarty->addMethodCall("registerPlugin", array(
                    $plugin->type,
                    $plugin->name,
                    array(
                        $plugin->class,
                        $plugin->method
                    )
                ));
            }*/
        }
    }
}