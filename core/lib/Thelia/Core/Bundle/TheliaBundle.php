<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Core\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Scope;

use Thelia\Core\DependencyInjection\Compiler\RegisterCouponPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterListenersPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterParserPluginPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterRouterPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterCouponConditionPass;
use Thelia\Core\DependencyInjection\Compiler\TranslatorPass;

/**
 * First Bundle use in Thelia
 * It initialize dependency injection container.
 *
 * @TODO load configuration from thelia plugin
 * @TODO register database configuration.
 *
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class TheliaBundle extends Bundle
{
    /**
     *
     * Construct the depency injection builder
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addScope(new Scope('request'));

        $container
            ->addCompilerPass(new TranslatorPass())
            ->addCompilerPass(new RegisterListenersPass())
            ->addCompilerPass(new RegisterParserPluginPass())
            ->addCompilerPass(new RegisterRouterPass())
            ->addCompilerPass(new RegisterCouponPass())
            ->addCompilerPass(new RegisterCouponConditionPass())
        ;
    }
}
