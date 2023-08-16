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
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Thelia\Api\Resource\ExtendResourceInterface;

class RegisterApiResourceExtendPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        try {
            $resourceExtends = $container->getParameter('Thelia.api.resource.extends');
        } catch (ParameterNotFoundException $e) {
            $resourceExtends = [];
        }

        /**
         * @var ExtendResourceInterface $class
         */
        foreach ($container->findTaggedServiceIds('thelia.api.resource.extend') as $class => $tag) {
            $resourceExtends[$class::getResourceToExtend()][] = $class;
        }

        $container->setParameter('Thelia.api.resource.extends', $resourceExtends);
    }
}
