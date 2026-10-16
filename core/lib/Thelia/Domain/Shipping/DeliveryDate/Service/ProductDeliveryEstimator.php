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

namespace Thelia\Domain\Shipping\DeliveryDate\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Shipping\DeliveryDate\DTO\ProductDeliveryEstimate;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\State;
use Thelia\Module\BaseModule;

/**
 * The delivery a product page announces before anything is in the cart.
 *
 * The destination is the delivery address of the cart, else the default address of its
 * customer, else the default country of the shop; the estimate says which country it was
 * made for. Among the active carriers that deliver there and announce an estimate, the
 * soonest wins, the product's own availability delay included. It is not the estimate of
 * the delivery step, which also knows the rest of the cart and the carrier the buyer picks.
 */
final readonly class ProductDeliveryEstimator
{
    public function __construct(
        private DeliveryTimeEstimator $deliveryTimeEstimator,
        private ProductVisibility $productVisibility,
    ) {
    }

    /**
     * The same for a product known by its id only, provided the visitor may see it: online,
     * and kept in their catalogue by the visibility rules (private sales, reserved ranges).
     * An id kept from an earlier page tells nothing about a product since taken away.
     */
    public function estimateById(int $productId, ?Cart $cart = null, ?\DateTimeImmutable $now = null): ?ProductDeliveryEstimate
    {
        $query = ProductQuery::create()->filterById($productId)->filterByVisible(1);
        $this->productVisibility->applyTo($query, ProductTableMap::COL_ID);
        $product = $query->findOne();

        return null === $product ? null : $this->estimate($product, $cart, $now);
    }

    public function estimate(Product $product, ?Cart $cart = null, ?\DateTimeImmutable $now = null): ?ProductDeliveryEstimate
    {
        if ($product->getVirtual()) {
            return null;
        }

        [$country, $state] = $this->destinationOf($cart);

        if (null === $country) {
            return null;
        }

        $carriers = ModuleQuery::create()
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
            ->orderByPosition()
            ->find();

        $soonest = null;

        foreach ($carriers as $carrier) {
            if (null === AreaDeliveryModuleQuery::create()->findByCountryAndModule($country, $carrier, $state)) {
                continue;
            }

            $estimate = $this->deliveryTimeEstimator->estimateForProduct($carrier, $product, $now);

            if (null !== $estimate && (null === $soonest || $estimate->isSoonerThan($soonest->estimate))) {
                $soonest = new ProductDeliveryEstimate($estimate, $country, $carrier);
            }
        }

        return $soonest;
    }

    /**
     * @return array{?Country, ?State}
     */
    private function destinationOf(?Cart $cart): array
    {
        $cartAddress = $cart?->getCartAddressRelatedByAddressDeliveryId();

        if (null !== $cartAddress?->getCountry()) {
            return [$cartAddress->getCountry(), $cartAddress->getState()];
        }

        $address = $cart?->getCustomer()?->getDefaultAddress();

        if (null !== $address?->getCountry()) {
            return [$address->getCountry(), $address->getState()];
        }

        return [CountryQuery::create()->findOneByByDefault(1), null];
    }
}
