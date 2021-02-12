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
use Thelia\Form\FormInterface;

class RegisterFormPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        try {
            $formConfig = $container->getParameter('Thelia.parser.forms');
        } catch (ParameterNotFoundException $e) {
            $formConfig = [];
        }

        foreach ($container->findTaggedServiceIds('thelia.form') as $id => $tag) {
            $formDefinition = $container->getDefinition($id);
            /** @var FormInterface $formClass */
            $formClass = $formDefinition->getClass();
            $name = $formClass::getName();

            $formConfig[$name] = $formDefinition->getClass();
        }

        $container->setParameter('Thelia.parser.forms', $formConfig);
    }
}
