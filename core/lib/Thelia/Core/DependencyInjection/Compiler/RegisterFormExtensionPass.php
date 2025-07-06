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
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class RegisterFormExtensionPass.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class RegisterFormExtensionPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @api
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('thelia.form_factory_builder')) {
            return;
        }

        $formFactoryBuilderDefinition = $container->getDefinition('thelia.form_factory_builder');

        /*
         * Add form extensions
         */
        foreach (array_keys($container->findTaggedServiceIds('thelia.forms.extension')) as $id) {
            $formFactoryBuilderDefinition
                ->addMethodCall('addExtension', [new Reference($id)]);
        }

        /*
         * And form types
         */
        foreach (array_keys($container->findTaggedServiceIds('thelia.form.type')) as $id) {
            $formFactoryBuilderDefinition
                ->addMethodCall('addType', [new Reference($id)]);
        }

        /*
         * And form type extensions
         */
        foreach (array_keys($container->findTaggedServiceIds('thelia.form.type_extension')) as $id) {
            $formFactoryBuilderDefinition
                ->addMethodCall('addTypeExtension', [new Reference($id)]);
        }
    }
}
