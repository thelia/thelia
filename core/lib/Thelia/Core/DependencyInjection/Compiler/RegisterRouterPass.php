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
namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 *
 * this compiler can add many router to symfony-cms routing
 *
 * Class RegisterRouterPass
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
            $modules = \Thelia\Model\ModuleQuery::getActivated();

            foreach ($modules as $module) {
                $moduleBaseDir = $module->getBaseDir();
                if (file_exists(THELIA_MODULE_DIR . "/" . $moduleBaseDir . "/Config/routing.xml")) {
                    $definition = new Definition(
                        $container->getParameter("router.class"),
                        array(
                            new Reference("router.module.xmlLoader"),
                            $moduleBaseDir . "/Config/routing.xml",
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

                    $chainRouter->addMethodCall("add", array(new Reference("router.".$moduleBaseDir), 150));
                }
            }
        }

    }
}
