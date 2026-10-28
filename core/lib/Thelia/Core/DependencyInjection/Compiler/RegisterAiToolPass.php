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
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Tool\Internal\AiToolDeclaration;
use Thelia\Domain\Ai\Tool\Internal\AiToolSchema;
use Thelia\Domain\Ai\Tool\Service\AiToolInstances;

/**
 * Collects the tools that services declare with #[AsAiTool] and checks every declaration.
 *
 * The kernel tags every autoconfigured service that carries the attribute or implements a tool interface (the
 * services of the modules included). This pass reads the declarations and stops the build when one breaks a rule
 * (a name outside the pattern or the prefix of the module, a right that is empty or unknown, a write that
 * asks for VIEW, a duplicate name...). It publishes `tool name => class` as the parameter
 * `thelia.ai.tool_classes` and gives AiToolInstances a locator of the tool services.
 */
final class RegisterAiToolPass implements CompilerPassInterface
{
    public const string TAG = 'thelia.ai.tool';

    /** Carried by every service that implements a tool interface, declared or not: an undeclared tool is a bug. */
    public const string INTERFACE_TAG = 'thelia.ai.tool_interface';

    public const string PARAMETER = 'thelia.ai.tool_classes';

    public function process(ContainerBuilder $container): void
    {
        $this->assertEveryToolIsDeclared($container);

        $classes = [];
        $references = [];

        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $serviceId) {
            $class = $this->classOf($container, $serviceId);

            if (null === $class) {
                continue;
            }

            $declaration = AiToolDeclaration::fromClass($class);
            $name = $declaration->name();
            $this->assertSchemaIsChecked($name, $class);

            if (isset($classes[$name])) {
                throw new InvalidAiArgumentException(\sprintf('The AI tool "%s" is declared twice, by "%s" and by "%s". Each tool name must be unique.', $name, $classes[$name], $class));
            }

            $classes[$name] = $class;
            $references[$name] = new Reference($serviceId);
        }

        ksort($classes);
        ksort($references);

        $container->setParameter(self::PARAMETER, $classes);

        if ($container->hasDefinition(AiToolInstances::class)) {
            $container->getDefinition(AiToolInstances::class)->setArgument('$locator', ServiceLocatorTagPass::register($container, $references));
        }
    }

    /**
     * The schema of a tool is read on an instance built without its constructor: a tool whose schema depends on its
     * services cannot be read here and is checked the first time it is offered (AiToolSchema::forTransport()).
     */
    private function assertSchemaIsChecked(string $name, string $class): void
    {
        try {
            $schema = (new \ReflectionClass($class))->newInstanceWithoutConstructor()->inputSchema();
        } catch (\Throwable) {
            return;
        }

        AiToolSchema::forTransport($name, $schema);
    }

    private function assertEveryToolIsDeclared(ContainerBuilder $container): void
    {
        $declared = $container->findTaggedServiceIds(self::TAG);

        foreach (array_keys($container->findTaggedServiceIds(self::INTERFACE_TAG)) as $serviceId) {
            $class = $this->classOf($container, $serviceId);

            if (null !== $class && !isset($declared[$serviceId])) {
                throw new InvalidAiArgumentException(\sprintf('"%s" implements a tool interface but carries no #[AsAiTool]: declare its name, description and right, or it stays invisible.', $class));
            }
        }
    }

    private function classOf(ContainerBuilder $container, string $serviceId): ?string
    {
        $class = $container->getParameterBag()->resolveValue($container->getDefinition($serviceId)->getClass() ?? $serviceId);

        return \is_string($class) && class_exists($class) ? $class : null;
    }
}
