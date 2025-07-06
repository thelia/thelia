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
use Thelia\Coupon\CouponManager;

/**
 * Class RegisterListenersPass
 * Source code come from Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RegisterKernelListenersPass class.
 *
 * Register all available Conditions for the coupon module
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class RegisterCouponConditionPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container Container
     *
     * @api
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CouponManager::class)) {
            return;
        }

        $couponManager = $container->getDefinition(CouponManager::class);
        $services = $container->findTaggedServiceIds('thelia.coupon.addCondition');

        foreach (array_keys($services) as $id) {
            $couponManager->addMethodCall(
                'addAvailableCondition',
                [
                    new Reference($id),
                ]
            );
        }
    }
}
