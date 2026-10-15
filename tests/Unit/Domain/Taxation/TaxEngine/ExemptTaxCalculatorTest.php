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

namespace Thelia\Tests\Unit\Domain\Taxation\TaxEngine;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Taxation\TaxEngine\ExemptTaxCalculator;
use Thelia\Domain\Taxation\TaxEngine\OrderProductTaxCollection;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\OrderProductTax;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;

final class ExemptTaxCalculatorTest extends TestCase
{
    public function testTheTaxedPriceIsTheUntaxedOneAndTheTaxCollectionComesBackEmpty(): void
    {
        $taxCollection = new OrderProductTaxCollection();
        $taxCollection->addTax(new OrderProductTax());

        self::assertSame(10.0, (new ExemptTaxCalculator())->getTaxedPrice(10.0, $taxCollection));
        self::assertTrue($taxCollection->isEmpty(), 'An exempt line is written with no tax row at all.');
    }

    public function testNoTaxCollectionIsBuiltForACallerThatAskedForNone(): void
    {
        $taxCollection = null;

        self::assertSame(10.0, (new ExemptTaxCalculator())->getTaxedPrice(10.0, $taxCollection));
        self::assertNull($taxCollection);
    }

    public function testEveryLoadingKeepsTheSameCalculator(): void
    {
        $calculator = new ExemptTaxCalculator();

        self::assertSame($calculator, $calculator->load(new Product(), new Country()));
        self::assertSame($calculator, $calculator->loadTaxRule(new TaxRule(), new Country(), new Product()));
        self::assertSame($calculator, $calculator->loadTaxRuleWithoutCountry(new TaxRule(), new Product()));
        self::assertSame($calculator, $calculator->loadTaxRuleWithoutProduct(new TaxRule(), new Country()));
    }

    public function testNoTaxAmountIsEverFoundAndTheTaxCollectionComesBackEmpty(): void
    {
        $taxCollection = new OrderProductTaxCollection();
        $taxCollection->addTax(new OrderProductTax());

        self::assertSame(0.0, (new ExemptTaxCalculator())->getTaxAmountFromUntaxedPrice(10.0, $taxCollection));
        self::assertTrue($taxCollection->isEmpty());
        self::assertSame(0.0, (new ExemptTaxCalculator())->getTaxAmountFromTaxedPrice(12.0));
    }

    public function testTheUntaxedPriceIsTheTaxedOne(): void
    {
        self::assertSame(12.5, (new ExemptTaxCalculator())->getUntaxedPrice(12.5));
    }

    public function testADiscountIsSpreadUntouched(): void
    {
        $cart = (new Cart())->setDiscount('10.500000');
        $order = (new Order())->setDiscount('7.250000');
        $calculator = new ExemptTaxCalculator();

        self::assertSame(10.5, $calculator->computeUntaxedCartDiscount($cart, new Country()));
        self::assertSame(7.25, $calculator->computeUntaxedOrderDiscount($order));
        self::assertSame(1.0, $calculator->computeCartTaxFactor($cart, new Country()));
        self::assertSame(1.0, $calculator->computeOrderTaxFactor($order));
    }
}
