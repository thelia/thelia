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

class RegisterCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        try {
            $commands = $container->getParameter('command.definition');
        } catch (ParameterNotFoundException) {
            $commands = [];
        }

        foreach (
            array_keys(array_merge(
                $container->findTaggedServiceIds('thelia.command'),
                $container->findTaggedServiceIds('console.command')
            )) as $id
        ) {
            $commandDefinition = $container->getDefinition($id);
            $commandDefinition->setPublic(true);
            $commands[] = $id;
        }

        $container->setParameter('command.definition', $commands);
    }
}
