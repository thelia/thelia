<?php

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use TheliaSmarty\Template\Plugins\TheliaLoop;

class RegisterCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        try {
            $commands = $container->getParameter("command.definition");
        } catch (ParameterNotFoundException $e) {
            $commands = [];
        }

        foreach ($container->findTaggedServiceIds("thelia.command") as $id => $tag) {
            $commandDefinition = $container->getDefinition($id);
            array_push($commands, $commandDefinition->getClass());
        }

        $container->setParameter("command.definition", $commands);
    }
}