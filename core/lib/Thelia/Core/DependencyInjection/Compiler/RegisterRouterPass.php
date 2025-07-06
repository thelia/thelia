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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * this compiler can add many router to symfony-cms routing.
 *
 * Class RegisterRouterPass
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class RegisterRouterPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @api
     */
    public function process(ContainerBuilder $container): void
    {
        try {
            $chainRouter = $container->getDefinition('router.chainRequest');
        } catch (InvalidArgumentException) {
            return;
        }

        $chainRouter->addMethodCall('add', [new Reference('router.rewrite'), 1024]);
        $chainRouter->addMethodCall('add', [new Reference('router.default'), 512]);

        foreach ($container->findTaggedServiceIds('router.register') as $id => $attributes) {
            $priority = $attributes[0]['priority'] ?? 0;
            $router = $container->getDefinition($id);
            $router->addMethodCall('setOption', ['cache_dir', THELIA_CACHE_DIR.$container->getParameter('kernel.environment').DS.'routing'.DS.$id]);

            $chainRouter->addMethodCall('add', [new Reference($id), $priority]);
        }
    }
}
