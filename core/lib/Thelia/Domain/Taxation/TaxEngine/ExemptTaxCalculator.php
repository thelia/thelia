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

namespace Thelia\Domain\Taxation\TaxEngine;

use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\Product;
use Thelia\Model\State;
use Thelia\Model\TaxRule;

/**
 * The calculator handed out for a sale that carries no VAT at all.
 *
 * It answers every question with the untaxed amount rather than looking a rate
 * up, which is what makes an exemption a property of the sale instead of a tax
 * rule the shop would have to create, assign and later explain on an old
 * invoice. The tax collection it fills stays empty, so an order built through
 * it is written with no order_product_tax row - and that absence is what keeps
 * the amounts frozen: the totals are read back from those rows, so nothing
 * recomputes them when the buyer's number is revoked a year later.
 *
 * It is handed out deliberately, by the call sites that know which cart or
 * order they are pricing. It is never returned by a factory reading ambient
 * state, because the catalogue is priced by the same calculator as the cart:
 * a calculator chosen from context would zero out the public prices a shop
 * writes for everyone.
 */
final class ExemptTaxCalculator implements TaxCalculatorInterface
{
    public function load(Product $product, Country $country, ?State $state = null): static
    {
        return $this;
    }

    public function loadTaxRule(TaxRule $taxRule, Country $country, Product $product, ?State $state = null): static
    {
        return $this;
    }

    public function loadTaxRuleWithoutCountry(TaxRule $taxRule, Product $product): static
    {
        return $this;
    }

    public function loadTaxRuleWithoutProduct(TaxRule $taxRule, Country $country, ?State $state = null): static
    {
        return $this;
    }

    public function getTaxAmountFromUntaxedPrice(float $untaxedPrice, ?OrderProductTaxCollection &$taxCollection = null): int|float
    {
        if ($taxCollection instanceof OrderProductTaxCollection) {
            $taxCollection = new OrderProductTaxCollection();
        }

        return 0.0;
    }

    public function getTaxAmountFromTaxedPrice($taxedPrice): int|float
    {
        return 0.0;
    }

    public function getTaxedPrice(float $untaxedPrice, ?OrderProductTaxCollection &$taxCollection = null, ?string $askedLocale = null): int|float
    {
        if ($taxCollection instanceof OrderProductTaxCollection) {
            $taxCollection = new OrderProductTaxCollection();
        }

        return $untaxedPrice;
    }

    public function getUntaxedPrice($taxedPrice): int|float
    {
        return $taxedPrice;
    }

    public function computeUntaxedCartDiscount(Cart $cart, Country $country, ?State $state = null): int|float
    {
        return (float) $cart->getDiscount();
    }

    public function computeUntaxedOrderDiscount(Order $order): int|float
    {
        return (float) $order->getDiscount();
    }

    /**
     * No tax to average, so the discount is spread untouched.
     */
    public function computeCartTaxFactor(Cart $cart, Country $country, ?State $state = null): float
    {
        return 1.0;
    }

    public function computeOrderTaxFactor(Order $order): float
    {
        return 1.0;
    }
}
