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

namespace Thelia\Tests\Integration\Domain\Taxation;

use Thelia\Domain\Taxation\TaxEngine\Calculator;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The tax factor spreads a discount over the VAT rates of what is being bought,
 * so it belongs to one order or one cart. A single request handles several of
 * them — the back-office order list walks the whole page.
 */
final class TaxFactorScopeTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    public function testEachOrderKeepsItsOwnTaxFactor(): void
    {
        $standardRate = $this->createDiscountedOrder('20.000000');
        $reducedRate = $this->createDiscountedOrder('5.500000');

        self::assertEqualsWithDelta(1.2, Calculator::getOrderTaxFactor($standardRate), 0.0001);
        self::assertEqualsWithDelta(
            1.055,
            Calculator::getOrderTaxFactor($reducedRate),
            0.0001,
            'The second order of the request must not inherit the first order tax factor.',
        );

        // 10 of discount on goods taxed at 5.5 % is 9.48 before tax, not 8.33.
        self::assertEqualsWithDelta(
            10 / 1.055,
            Calculator::getUntaxedOrderDiscount($reducedRate),
            0.0001,
        );
    }

    public function testEachCartKeepsItsOwnTaxFactor(): void
    {
        $country = $this->createFixtureFactory()->country();

        $standardRate = $this->createDiscountedCart($country, '20');
        $reducedRate = $this->createDiscountedCart($country, '5.5');

        self::assertEqualsWithDelta(1.2, Calculator::getCartTaxFactor($standardRate, $country), 0.0001);
        self::assertEqualsWithDelta(
            1.055,
            Calculator::getCartTaxFactor($reducedRate, $country),
            0.0001,
            'The second cart of the request must not inherit the first cart tax factor.',
        );

        self::assertEqualsWithDelta(
            10 / 1.055,
            Calculator::getUntaxedCartDiscount($reducedRate, $country),
            0.0001,
        );
    }

    public function testTheCartTaxFactorFollowsTheDeliveryCountry(): void
    {
        $factory = $this->createFixtureFactory();
        $standardCountry = $factory->country(['isocode' => '998', 'isoalpha2' => 'XS', 'isoalpha3' => 'XSX']);
        $reducedCountry = $factory->country(['isocode' => '997', 'isoalpha2' => 'XR', 'isoalpha3' => 'XRX']);

        $taxRule = $this->createTaxRule($standardCountry, '20');
        $this->addTaxToRule($taxRule, $reducedCountry, '5.5');
        $cart = $this->createDiscountedCartOf($factory->product($factory->category(), $taxRule, $factory->currency()));

        self::assertEqualsWithDelta(1.2, (new Calculator())->computeCartTaxFactor($cart, $standardCountry), 0.0001);
        self::assertEqualsWithDelta(
            1.055,
            (new Calculator())->computeCartTaxFactor($cart, $reducedCountry),
            0.0001,
            'The same cart shipped elsewhere is taxed at the rate of its new destination.',
        );
    }

    public function testTheCartTaxFactorFollowsTheProductsInTheCart(): void
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country();
        $standardRated = $factory->product($factory->category(), $this->createTaxRule($country, '20'), $factory->currency());
        $reducedRated = $factory->product($factory->category(), $this->createTaxRule($country, '5.5'), $factory->currency());

        $cart = $this->createDiscountedCartOf($standardRated);

        self::assertEqualsWithDelta(1.2, (new Calculator())->computeCartTaxFactor($cart, $country), 0.0001);

        $cart->getCartItems()->getFirst()
            ->setProductId($reducedRated->getId())
            ->setProductSaleElementsId($reducedRated->getDefaultSaleElements()->getId())
            ->save($this->getPropelConnection());

        self::assertEqualsWithDelta(
            1.055,
            (new Calculator())->computeCartTaxFactor($cart, $country),
            0.0001,
            'A cart whose content changed must not keep the factor of what it held before.',
        );
    }

    public function testAskingAgainForTheTaxFactorOfAnUnchangedCartReadsNoProductAgain(): void
    {
        $country = $this->createFixtureFactory()->country();
        $cart = $this->createDiscountedCart($country, '20');

        self::assertEqualsWithDelta(1.2, (new Calculator())->computeCartTaxFactor($cart, $country), 0.0001);

        $statements = $this->recordSqlQueries(static function () use ($cart, $country): void {
            self::assertEqualsWithDelta(1.2, (new Calculator())->computeCartTaxFactor($cart, $country), 0.0001);
        });

        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'product'));
    }

    private function createDiscountedOrder(string $taxAmount): Order
    {
        $order = $this->createFixtureFactory()->order();
        $order->setDiscount('10.000000')->save($this->getPropelConnection());

        $orderProduct = new OrderProduct();
        $orderProduct
            ->setOrderId($order->getId())
            ->setProductRef('taxed-ref')
            ->setProductSaleElementsRef('taxed-pse-ref')
            ->setTitle('Taxed product')
            ->setQuantity(1.0)
            ->setPrice('100.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());

        (new OrderProductTax())
            ->setOrderProductId($orderProduct->getId())
            ->setTitle('VAT')
            ->setDescription('')
            ->setAmount($taxAmount)
            ->save($this->getPropelConnection());

        return $order;
    }

    private function createDiscountedCart(Country $country, string $percent): Cart
    {
        $factory = $this->createFixtureFactory();

        return $this->createDiscountedCartOf(
            $factory->product($factory->category(), $this->createTaxRule($country, $percent), $factory->currency()),
        );
    }

    private function createDiscountedCartOf(Product $product): Cart
    {
        $factory = $this->createFixtureFactory();

        $cart = $factory->cart();
        $cart->setDiscount('10.000000')->save($this->getPropelConnection());

        (new CartItem())
            ->setCart($cart)
            ->setProduct($product)
            ->setProductSaleElements($product->getDefaultSaleElements())
            ->setQuantity(1)
            ->setPrice('100')
            ->setPromoPrice('100')
            ->setPromo(0)
            ->save($this->getPropelConnection());

        return $cart;
    }

    private function createTaxRule(Country $country, string $percent): TaxRule
    {
        $factory = $this->createFixtureFactory();
        // A non-empty override array forces a rule of its own instead of reusing the seeded one.
        $taxRule = $factory->taxRule(['isDefault' => false]);
        $this->addTaxToRule($taxRule, $country, $percent);

        return $taxRule;
    }

    private function addTaxToRule(TaxRule $taxRule, Country $country, string $percent): void
    {
        $tax = $this->createFixtureFactory()->tax(['requirements' => ['percent' => $percent], 'title' => 'VAT '.$percent]);

        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());
    }
}
