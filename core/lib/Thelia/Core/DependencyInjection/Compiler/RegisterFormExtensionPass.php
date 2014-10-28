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
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class RegisterFormExtensionPass
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class RegisterFormExtensionPass implements CompilerPassInterface
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
        if (!$container->hasDefinition("thelia.form_factory_builder")) {
            return;
        }

        $formFactoryBuilderDefinition = $container->getDefinition("thelia.form_factory_builder");

        /**
         * Add form extensions
         */
        foreach ($container->findTaggedServiceIds("thelia.forms.extension") as $id => $definition) {
            $formFactoryBuilderDefinition
                ->addMethodCall("addExtension", [new Reference($id)]);
        }

        /**
         * And form types
         */
        foreach ($container->findTaggedServiceIds("thelia.form.type") as $id => $definition) {
            $formFactoryBuilderDefinition
                ->addMethodCall("addType", [new Reference($id)]);
        }

        /**
         * And form type extensions
         */
        foreach ($container->findTaggedServiceIds("thelia.form.type_extension") as $id => $definition) {
            $formFactoryBuilderDefinition
                ->addMethodCall("addTypeExtension", [new Reference($id)]);
        }
    }
}
