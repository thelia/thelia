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

namespace Thelia\Tests\Support\Taxation;

use Thelia\Domain\Taxation\TaxEngine\OrderProductTaxCollection;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\Product;
use Thelia\Model\State;
use Thelia\Model\TaxRule;

/**
 * A tax calculator applying one flat rate, so that the taxed and the untaxed price
 * are distinguishable and a round trip through the tax can be verified by hand.
 */
final class FlatRateTaxCalculator implements TaxCalculatorInterface
{
    public function __construct(private readonly float $rate = 0.2)
    {
    }

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
        return $untaxedPrice * $this->rate;
    }

    public function getTaxAmountFromTaxedPrice($taxedPrice): int|float
    {
        return $taxedPrice - $taxedPrice / (1 + $this->rate);
    }

    public function getTaxedPrice(float $untaxedPrice, ?OrderProductTaxCollection &$taxCollection = null, ?string $askedLocale = null): int|float
    {
        return $untaxedPrice * (1 + $this->rate);
    }

    public function getUntaxedPrice($taxedPrice): int|float
    {
        return $taxedPrice / (1 + $this->rate);
    }

    public function computeUntaxedCartDiscount(Cart $cart, Country $country, ?State $state = null): int|float
    {
        return 0;
    }

    public function computeUntaxedOrderDiscount(Order $order): int|float
    {
        return 0;
    }

    public function computeCartTaxFactor(Cart $cart, Country $country, ?State $state = null): float
    {
        return 1 + $this->rate;
    }

    public function computeOrderTaxFactor(Order $order): float
    {
        return 1 + $this->rate;
    }
}
