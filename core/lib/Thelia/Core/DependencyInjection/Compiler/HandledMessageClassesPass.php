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

/**
 * Lists the message classes a handler of the application takes, for
 * AllowedClassesSerializer: a queued class nobody handles is never built.
 *
 * Read from the handler locators MessengerPass registers for each bus, so it runs
 * after it.
 */
final class HandledMessageClassesPass implements CompilerPassInterface
{
    public const PARAMETER = 'thelia.messenger.handled_message_classes';

    public function process(ContainerBuilder $container): void
    {
        $classes = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_ends_with($id, '.messenger.handlers_locator')) {
                continue;
            }

            $mapping = $definition->getArgument(0);

            if (\is_array($mapping)) {
                array_push($classes, ...array_map('strval', array_keys($mapping)));
            }
        }

        $container->setParameter(self::PARAMETER, array_values(array_unique($classes)));
    }
}
