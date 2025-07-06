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
use Symfony\Component\DependencyInjection\Reference;
use Thelia\Core\Translation\Translator;

/**
 * Class TranslatorPass.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class TranslatorPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @api
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(Translator::class)) {
            return;
        }

        $translator = $container->getDefinition(Translator::class);

        foreach ($container->findTaggedServiceIds('translation.loader') as $id => $attributes) {
            $translator->addMethodCall('addLoader', [$attributes[0]['alias'], new Reference($id)]);
            if (isset($attributes[0]['legacy-alias'])) {
                $translator->addMethodCall('addLoader', [$attributes[0]['legacy-alias'], new Reference($id)]);
            }
        }
    }
}
