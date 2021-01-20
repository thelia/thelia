<?php

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

        foreach ($container->findTaggedServiceIds("thelia.loop") as $id => $tag) {
            $loopDefinition = $container->getDefinition($id);
            $smarty->addMethodCall("registerLoop", [$loopDefinition->getClass()]);
        }
    }
}