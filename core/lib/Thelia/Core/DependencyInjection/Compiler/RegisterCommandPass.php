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

class RegisterCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        try {
            $commands = $container->getParameter('command.definition');
        } catch (ParameterNotFoundException $e) {
            $commands = [];
        }

        foreach ($container->findTaggedServiceIds('thelia.command') as $id => $tag) {
            $commandDefinition = $container->getDefinition($id);
            array_push($commands, $commandDefinition->getClass());
        }

        $container->setParameter('command.definition', $commands);
    }
}
