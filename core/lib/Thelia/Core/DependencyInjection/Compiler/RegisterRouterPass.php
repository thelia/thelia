<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
            $router->addMethodCall("setOption", ["cache_dir", THELIA_CACHE_DIR . $container->getParameter("kernel.environment") . DS . "routing" . DS . $id]);

            $chainRouter->addMethodCall("add", [new Reference($id), $priority]);
        }
        if (\defined("THELIA_INSTALL_MODE") === false) {
            $modules = ModuleQuery::getActivated();

            /** @var Module $module */
            foreach ($modules as $module) {
                $moduleBaseDir = $module->getBaseDir();
                $routingConfigFilePath = $module->getAbsoluteBaseDir() . DS . "Config" . DS . "routing.xml";

                if (file_exists($routingConfigFilePath)) {
                    $moduleRouter = new Definition(
                        $container->getParameter("router.class"),
                        [
                            new Reference("router.module.xmlLoader"),
                            $routingConfigFilePath,
                            [],
                            new Reference("request.context")
                        ]
                    );

                    $routerId ="router.".$moduleBaseDir;
                    $container->setDefinition($routerId, $moduleRouter);

                    $moduleRouter->addMethodCall("setOption", ["cache_dir", THELIA_CACHE_DIR . $container->getParameter("kernel.environment") . DS . "routing" . DS . $routerId]);
                    $chainRouter->addMethodCall("add", [new Reference($routerId), 150 + $module->getPosition()]);
                }
            }
        }
    }
}
