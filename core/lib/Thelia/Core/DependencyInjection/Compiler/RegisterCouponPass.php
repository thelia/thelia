<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class RegisterListenersPass
 * Source code come from Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RegisterKernelListenersPass class
 *
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RegisterCouponPass implements CompilerPassInterface
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
        $services = $container->findTaggedServiceIds("thelia.coupon.addCoupon");

        foreach ($services as $id => $rule) {
            $couponManager->addMethodCall(
                'addAvailableCoupon',
                array(
                    new Reference($id)
                )
            );
        }
    }
}
