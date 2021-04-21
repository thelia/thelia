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
use TheliaSmarty\Template\Plugins\TheliaLoop;

class RegisterLoopPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(TheliaLoop::class)) {
            return;
        }

        try {
            $loopConfig = $container->getParameter('Thelia.parser.loops');
        } catch (ParameterNotFoundException $e) {
            $loopConfig = [];
        }

        foreach ($container->findTaggedServiceIds('thelia.loop') as $id => $tag) {
            $loopDefinition = $container->getDefinition($id);
            $classParts = explode('\\', $loopDefinition->getClass());
            $name = strtolower(strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', end($classParts))));

            $loopConfig[$name] = $loopDefinition->getClass();
        }

        $container->setParameter('Thelia.parser.loops', $loopConfig);
    }
}
