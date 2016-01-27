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
/*************************************************************************************/

namespace Thelia\Module;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Class AbastractAdminResourcesCompiler
 * @package Thelia\Module
 * @since 2.3
 * @author Penalver Antony <apenalver@openstudio.fr>
 */
abstract class AbstractAdminResourcesCompiler implements CompilerPassInterface
{
    /**
     * @return Array of resources
     * Exemple :
     * [
     *      "ADDRESS" => "admin.address",
     *      ...
     * ]
     */
    abstract public function getResources();

    /**
     * @return string ModuleCode
     */
    abstract public function getModuleCode();

    /**
     * Allow module to add resources in AdminResources Service
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */
    public function process(\Symfony\Component\DependencyInjection\ContainerBuilder $container)
    {
        if (!$container->hasDefinition("thelia.admin.resources")) {
            return;
        }

        /** @var \Symfony\Component\DependencyInjection\Definition $adminResources */
        $adminResources = $container->getDefinition("thelia.admin.resources");

        $adminResources->addMethodCall("addModuleResources", [$this->getResources(), $this->getModuleCode()]);
    }
}
