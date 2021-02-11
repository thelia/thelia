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

namespace TheliaSmarty\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use TheliaSmarty\Template\SmartyParser;

/**
 * Register parser plugins. These plugins shouild be tagged thelia.parser.register_plugin
 * in the configuration.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class RegisterParserPluginPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(SmartyParser::class)) {
            return;
        }

        $smarty = $container->getDefinition(SmartyParser::class);

        foreach ($container->findTaggedServiceIds("thelia.parser.register_plugin") as $id => $plugin) {
            $smarty->addMethodCall("addPlugins", [new Reference($id)]);
        }

        $smarty->addMethodCall("registerPlugins");
    }
}
