<?php

declare(strict_types=1);

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Thelia\Core\Template\Element\BaseLoop;

class LoopCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        try {
            $loopConfig = $container->getParameter('Thelia.parser.loops');
        } catch (ParameterNotFoundException) {
            $loopConfig = [];
        }

        $taggedServices = $container->findTaggedServiceIds('thelia.loop');

        foreach ($taggedServices as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $className = $definition->getClass();

            if ($className && is_subclass_of($className, BaseLoop::class)) {
                $loopName = $this->getLoopNameFromClass($className);

                $loopConfig[$loopName] = $className;
            }
        }

        $container->setParameter('Thelia.parser.loops', $loopConfig);
    }

    private function getLoopNameFromClass(string $className): string
    {
        $parts = explode('\\', $className);
        $shortClassName = end($parts);

        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $shortClassName));
    }
}
