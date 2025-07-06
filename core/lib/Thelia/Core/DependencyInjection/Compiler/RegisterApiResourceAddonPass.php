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

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Thelia\Api\Resource\ResourceAddonInterface;

class RegisterApiResourceAddonPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        try {
            $resourceAddons = $container->getParameter('Thelia.api.resource.addons');
        } catch (ParameterNotFoundException) {
            $resourceAddons = [];
        }

        /**
         * @var ResourceAddonInterface $class
         */
        foreach (array_keys($container->findTaggedServiceIds('thelia.api.resource.addon')) as $class) {
            $reflection = new \ReflectionClass($class);
            $resourceAddons[$class::getResourceParent()][$reflection->getShortName()] = $class;
        }

        $container->setParameter('Thelia.api.resource.addons', $resourceAddons);
    }
}
