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

namespace Thelia\Module;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Class AbastractAdminResourcesCompiler.
 *
 * @since 2.3
 *
 * @author Penalver Antony <apenalver@openstudio.fr>
 */
abstract class AbstractAdminResourcesCompiler implements CompilerPassInterface
{
    /**
     * @return array of resources
     *               Exemple :
     *               [
     *               "ADDRESS" => "admin.address",
     *               ...
     *               ]
     */
    abstract public function getResources();

    /**
     * @return string ModuleCode
     */
    abstract public function getModuleCode();

    /**
     * Allow module to add resources in AdminResources Service.
     */
    public function process(\Symfony\Component\DependencyInjection\ContainerBuilder $container)
    {
        if (!$container->hasDefinition('thelia.admin.resources')) {
            return;
        }

        /** @var \Symfony\Component\DependencyInjection\Definition $adminResources */
        $adminResources = $container->getDefinition('thelia.admin.resources');

        $adminResources->addMethodCall('addModuleResources', [$this->getResources(), $this->getModuleCode()]);
    }
}
