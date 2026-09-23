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

namespace Thelia\Domain\Taxation\Service;

use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\ModuleQuery;
use Thelia\Model\State;
use Thelia\Module\AbstractDeliveryModule;

/**
 * The VAT a cart would have carried had it not been exempted.
 *
 * An exempt order writes no tax line at all, so the figure an invoice has to
 * state - the VAT the buyer accounts for himself - cannot be read back from the
 * order afterwards. It is computed here, once, while the cart is still around,
 * and frozen on the invoice address.
 *
 * Everything goes through the factory rather than through the cart and its
 * lines: their calculators resolve to the exempt one for this very cart, which
 * would answer zero to every question asked here.
 */
readonly class ExemptedVatCalculator
{
    public function __construct(
        private TaxCalculatorFactoryInterface $taxCalculatorFactory,
    ) {
    }

    /**
     * @throws PropelException
     */
    public function forCart(
        Cart $cart,
        Country $country,
        ?State $state,
        float $untaxedPostage,
        ?int $deliveryModuleId,
        ?string $locale,
    ): float {
        $vat = 0.0;

        foreach ($cart->getCartItems() as $cartItem) {
            $untaxedPrice = 1 === (int) $cartItem->getPromo()
                ? (float) $cartItem->getPromoPrice()
                : (float) $cartItem->getPrice();

            $taxedPrice = $this->taxCalculatorFactory->createTaxCalculator()
                ->load($cartItem->getProduct(), $country, $state)
                ->getTaxedPrice($untaxedPrice);

            $vat += ($taxedPrice - $untaxedPrice) * $cartItem->getQuantity();
        }

        // A discount is stored taxed, and a taxed order spreads it over the
        // rates of the goods. The share of it that was VAT is not exempted
        // twice: it never reached the buyer.
        $discount = (float) $cart->getDiscount();

        if (0.0 !== $discount) {
            $vat -= $discount - $this->taxCalculatorFactory->createTaxCalculator()
                ->computeUntaxedCartDiscount($cart, $country, $state);
        }

        $vat += $this->postageVat($untaxedPostage, $country, $deliveryModuleId, $locale);

        return round(max(0.0, $vat), 2);
    }

    /**
     * @throws PropelException
     */
    private function postageVat(
        float $untaxedPostage,
        Country $country,
        ?int $deliveryModuleId,
        ?string $locale,
    ): float {
        if (0.0 === $untaxedPostage || null === $deliveryModuleId) {
            return 0.0;
        }

        $module = ModuleQuery::create()->findPk($deliveryModuleId)?->createInstance();

        // Only a module built on the shipped base class exposes the rule it
        // taxes its carriage with. Another one quoted a postage Thelia cannot
        // re-quote without it, so the carriage is left out rather than taxed
        // under a rule it never used.
        if (!$module instanceof AbstractDeliveryModule) {
            return 0.0;
        }

        return (float) $module->buildOrderPostage($untaxedPostage, $country, $locale)->getAmountTax();
    }
}
