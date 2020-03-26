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
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Thelia\Core\DependencyInjection\Compiler\CurrencyConverterProviderPass;
use Thelia\Core\DependencyInjection\Compiler\FallbackParserPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterArchiverPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterAssetFilterPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterCouponPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterSerializerPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterFormExtensionPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterHookListenersPass;
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
 * @author Manuel Raynaud <manu@raynaud.io>
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

        $container
            ->addCompilerPass(new FallbackParserPass())
            ->addCompilerPass(new TranslatorPass())
            ->addCompilerPass(new RegisterHookListenersPass(), PassConfig::TYPE_AFTER_REMOVING)
            ->addCompilerPass(new RegisterRouterPass())
            ->addCompilerPass(new RegisterCouponPass())
            ->addCompilerPass(new RegisterCouponConditionPass())
            ->addCompilerPass(new RegisterArchiverPass)
            ->addCompilerPass(new RegisterAssetFilterPass())
            ->addCompilerPass(new RegisterSerializerPass)
            ->addCompilerPass(new StackPass())
            ->addCompilerPass(new RegisterFormExtensionPass())
            ->addCompilerPass(new CurrencyConverterProviderPass())
            ->addCompilerPass(new RegisterListenersPass())
        ;
    }
}
