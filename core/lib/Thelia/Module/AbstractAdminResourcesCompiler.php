<?php

declare(strict_types=1);

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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Class AbastractAdminResourcesCompiler.
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
    abstract public function getResources(): array;

    /**
     * @return string ModuleCode
     */
    abstract public function getModuleCode(): string;

    /**
     * Allow module to add resources in AdminResources Service.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('thelia.admin.resources')) {
            return;
        }

        /** @var Definition $adminResources */
        $adminResources = $container->getDefinition('thelia.admin.resources');

        $adminResources->addMethodCall('addModuleResources', [$this->getResources(), $this->getModuleCode()]);
    }
}
