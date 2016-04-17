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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

/**
 *
 * this compiler can add many router to symfony-cms routing
 *
 * Class RegisterRouterPass
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class RegisterRouterPass implements CompilerPassInterface
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
        try {
            $chainRouter = $container->getDefinition("router.chainRequest");
        } catch (InvalidArgumentException $e) {
            return;
        }

        foreach ($container->findTaggedServiceIds("router.register") as $id => $attributes) {
            $priority = isset($attributes[0]["priority"]) ? $attributes[0]["priority"] : 0;
            $router = $container->getDefinition($id);
            $router->addMethodCall("setOption", array("matcher_cache_class", $container::camelize("ProjectUrlMatcher".$id)));
            $router->addMethodCall("setOption", array("generator_cache_class", $container::camelize("ProjectUrlGenerator".$id)));

            $chainRouter->addMethodCall("add", array(new Reference($id), $priority));
        }
        if (defined("THELIA_INSTALL_MODE") === false) {
            $modules = ModuleQuery::getActivated();

            /** @var Module $module */
            foreach ($modules as $module) {
                $moduleBaseDir = $module->getBaseDir();
                $routingConfigFilePath = $module->getAbsoluteBaseDir() . DS . "Config" . DS . "routing.xml";

                if (file_exists($routingConfigFilePath)) {
                    $definition = new Definition(
                        $container->getParameter("router.class"),
                        array(
                            new Reference("router.module.xmlLoader"),
                            $routingConfigFilePath,
                            array(
                                "cache_dir" => $container->getParameter("kernel.cache_dir"),
                                "debug" => $container->getParameter("kernel.debug"),
                                "matcher_cache_class" => $container::camelize("ProjectUrlMatcher".$moduleBaseDir),
                                "generator_cache_class" => $container::camelize("ProjectUrlGenerator".$moduleBaseDir),
                            ),
                            new Reference("request.context")
                        )
                    );

                    $container->setDefinition("router.".$moduleBaseDir, $definition);

                    $chainRouter->addMethodCall("add", array(new Reference("router.".$moduleBaseDir), 150 + $module->getPosition()));
                }
            }
        }
    }
}
