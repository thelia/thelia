<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class RegisterListenersPass
 * Source code come from Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RegisterKernelListenersPass class
 *
 * Register all available Conditions for the coupon module
 *
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
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
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('thelia.coupon.manager')) {
            return;
        }

        $couponManager = $container->getDefinition('thelia.coupon.manager');
        $services = $container->findTaggedServiceIds("thelia.coupon.addCondition");

        foreach ($services as $id => $condition) {
            $couponManager->addMethodCall(
                'addAvailableCondition',
                array(
                    new Reference($id)
                )
            );
        }
    }
}
