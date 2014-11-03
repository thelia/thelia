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

namespace Thelia\Core\Bundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Scope;

use Thelia\Core\DependencyInjection\Compiler\CurrencyConverterProviderPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterArchiveBuilderPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterCouponPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterFormatterPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterFormExtensionPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterListenersPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterRouterPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterCouponConditionPass;
use Thelia\Core\DependencyInjection\Compiler\StackPass;
use Thelia\Core\DependencyInjection\Compiler\TranslatorPass;

/**
 * First Bundle use in Thelia
 * It initialize dependency injection container.
 *
 * @TODO load configuration from thelia plugin
 * @TODO register database configuration.
 *
 *
 * @author Manuel Raynaud <manu@thelia.net>
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
            ->addCompilerPass(new RegisterListenersPass(), PassConfig::TYPE_AFTER_REMOVING)
            ->addCompilerPass(new RegisterRouterPass())
            ->addCompilerPass(new RegisterCouponPass())
            ->addCompilerPass(new RegisterCouponConditionPass())
            ->addCompilerPass(new RegisterArchiveBuilderPass())
            ->addCompilerPass(new RegisterFormatterPass())
            ->addCompilerPass(new StackPass())
            ->addCompilerPass(new RegisterFormExtensionPass())
            ->addCompilerPass(new CurrencyConverterProviderPass())
        ;
    }
}
