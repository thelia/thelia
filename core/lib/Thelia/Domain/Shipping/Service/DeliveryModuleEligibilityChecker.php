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
use Thelia\Domain\Shipping\ProductRule\CartShippingRules;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\State;
use Thelia\Module\BaseModule;

final readonly class DeliveryModuleEligibilityChecker
{
    public function __construct(
        private ContainerInterface $container,
        private CartShippingRules $cartShippingRules,
    ) {
    }

    /**
     * @param string|null $zipCode the postal code of the delivery address, checked against the
     *                             postal code rules of the zones; null when it is not known
     */
    public function isEligible(Module $module, Cart $cart, Country $country, ?State $state, ?string $zipCode = null): bool
    {
        // A product of the cart the carrier cannot take rules it out, whatever it serves.
        if ($this->cartShippingRules->for($cart)->excludes((int) $module->getId())) {
            return false;
        }

        return $this->servesTheCart($module, $cart, $country, $state, $zipCode);
    }

    /**
     * The products of the cart that leave it with no carrier at all: every active carrier
     * that serves the address is excluded by one of them. Empty while one carrier is
     * left, and when no carrier serves the address anyway, which is not their doing.
     *
     * @param string|null $zipCode the postal code of the delivery address: a carrier the
     *                             postal code rules turn away does not serve the address
     *
     * @return list<int> product ids
     */
    public function productsBlockingEveryCarrier(Cart $cart, Country $country, ?State $state, ?string $zipCode = null): array
    {
        $rules = $this->cartShippingRules->for($cart);
        $blockingProductIds = [];
        $servingCarrierCount = 0;

        foreach (ModuleQuery::create()->filterByActivate(1)->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->find() as $module) {
            if (!$this->servesTheCart($module, $cart, $country, $state, $zipCode)) {
                continue;
            }

            ++$servingCarrierCount;

            if (!$rules->excludes((int) $module->getId())) {
                return [];
            }

            array_push($blockingProductIds, ...$rules->productsExcluding((int) $module->getId()));
        }

        return 0 === $servingCarrierCount ? [] : array_values(array_unique($blockingProductIds));
    }

    private function servesTheCart(Module $module, Cart $cart, Country $country, ?State $state, ?string $zipCode): bool
    {
        $isCartVirtual = $cart->isVirtual();

        $areaDeliveryModule = AreaDeliveryModuleQuery::create()
            ->findByCountryAndModule($country, $module, $state, $zipCode);

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
