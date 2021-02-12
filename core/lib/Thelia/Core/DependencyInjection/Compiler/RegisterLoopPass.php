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
use TheliaSmarty\Template\Plugins\TheliaLoop;

class RegisterLoopPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(TheliaLoop::class)) {
            return;
        }

        $smarty = $container->getDefinition(TheliaLoop::class);

        foreach ($container->findTaggedServiceIds('thelia.loop') as $id => $tag) {
            $loopDefinition = $container->getDefinition($id);
            $smarty->addMethodCall('registerLoop', [$loopDefinition->getClass()]);
        }
    }
}
