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

namespace Thelia\Tests\Unit\Domain\Sale;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Sale\SaleDiscountCalculator;
use Thelia\Domain\Taxation\TaxEngine\OrderProductTaxCollection;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\Product;
use Thelia\Model\Sale;
use Thelia\Model\State;
use Thelia\Model\TaxRule;

/**
 * The discount of a sale operation is taken off the TAXED price, then converted
 * back: a 10% operation has to take 10% off what the customer actually pays,
 * not 10% off the untaxed price, which would not be the same number once the
 * tax is added back on a rounded figure.
 */
final class SaleDiscountCalculatorTest extends TestCase
{
    public function testAnAmountOffsetIsTakenOffTheTaxedPrice(): void
    {
        // 100 HT -> 120 TTC, minus 12 -> 108 TTC -> 90 HT
        self::assertSame(
            90.0,
            $this->calculator()->computeUntaxedPromoPrice(
                100.0,
                Sale::OFFSET_TYPE_AMOUNT,
                12.0,
                $this->taxCalculator(),
            ),
        );
    }

    public function testAPercentageOffsetIsTakenOffTheTaxedPrice(): void
    {
        // 100 HT -> 120 TTC, minus 10% -> 108 TTC -> 90 HT
        self::assertSame(
            90.0,
            $this->calculator()->computeUntaxedPromoPrice(
                100.0,
                Sale::OFFSET_TYPE_PERCENTAGE,
                10.0,
                $this->taxCalculator(),
            ),
        );
    }

    /**
     * An offset type the shop does not know about must leave the price alone
     * rather than guess, so a bad row in `sale` never gives products away.
     */
    public function testAnUnknownOffsetTypeLeavesThePriceUntouched(): void
    {
        self::assertSame(
            100.0,
            $this->calculator()->computeUntaxedPromoPrice(
                100.0,
                999,
                50.0,
                $this->taxCalculator(),
            ),
        );
    }

    /**
     * An amount larger than the price is floored at zero, never turned into a
     * negative price the shop would owe the customer.
     */
    public function testAnAmountOffsetLargerThanThePriceIsFlooredAtZero(): void
    {
        self::assertSame(
            0.0,
            $this->calculator()->computeUntaxedPromoPrice(
                100.0,
                Sale::OFFSET_TYPE_AMOUNT,
                500.0,
                $this->taxCalculator(),
            ),
        );
    }

    public function testAFullPercentageOffsetGivesAZeroPrice(): void
    {
        self::assertSame(
            0.0,
            $this->calculator()->computeUntaxedPromoPrice(
                100.0,
                Sale::OFFSET_TYPE_PERCENTAGE,
                100.0,
                $this->taxCalculator(),
            ),
        );
    }

    private function calculator(): SaleDiscountCalculator
    {
        return new SaleDiscountCalculator();
    }

    /**
     * A 20% VAT calculator, so that the taxed and the untaxed price are
     * distinguishable and the round trip is verifiable.
     */
    private function taxCalculator(): TaxCalculatorInterface
    {
        return new class implements TaxCalculatorInterface {
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
                return $untaxedPrice * 0.2;
            }

            public function getTaxAmountFromTaxedPrice($taxedPrice): int|float
            {
                return $taxedPrice - $taxedPrice / 1.2;
            }

            public function getTaxedPrice(float $untaxedPrice, ?OrderProductTaxCollection &$taxCollection = null, ?string $askedLocale = null): int|float
            {
                return $untaxedPrice * 1.2;
            }

            public function getUntaxedPrice($taxedPrice): int|float
            {
                return $taxedPrice / 1.2;
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
                return 1.0;
            }

            public function computeOrderTaxFactor(Order $order): float
            {
                return 1.0;
            }
        };
    }
}
