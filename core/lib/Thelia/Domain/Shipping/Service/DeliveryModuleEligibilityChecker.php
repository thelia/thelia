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

namespace Thelia\Domain\Shipping\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\State;

final readonly class DeliveryModuleEligibilityChecker
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function isEligible(Module $module, Cart $cart, Country $country, ?State $state): bool
    {
        $isCartVirtual = $cart->isVirtual();

        $areaDeliveryModule = AreaDeliveryModuleQuery::create()
            ->findByCountryAndModule($country, $module, $state);

        if (false === $isCartVirtual && null === $areaDeliveryModule) {
            return false;
        }

        $moduleInstance = $module->getDeliveryModuleInstance($this->container);

        if (true === $isCartVirtual && false === $moduleInstance->handleVirtualProductDelivery()) {
            return false;
        }

        return true;
    }
}
