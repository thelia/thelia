<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\TaxEngine;

use PHPUnit\Framework\TestCase;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\ProductQuery;
use Thelia\Model\Tax;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\TaxEngine\Calculator;

/**
 * The tax factor spreads a discount over the VAT rates of what is being bought,
 * so it belongs to one order or one cart. A single request handles several of
 * them: the back-office order list walks a whole page.
 */
class TaxFactorScopeTest extends TestCase
{
    /**
     * @var array<\Thelia\Model\Tax|\Thelia\Model\TaxRule|\Thelia\Model\TaxRuleCountry>
     */
    private $createdRows = [];

    protected function tearDown(): void
    {
        // Reverse creation order: the link row references both the rule and the tax.
        foreach (array_reverse($this->createdRows) as $row) {
            $row->delete();
        }

        $this->createdRows = [];
    }

    public function testEachOrderKeepsItsOwnTaxFactor(): void
    {
        $standardRate = $this->createDiscountedOrder('20.000000');
        $reducedRate = $this->createDiscountedOrder('5.500000');

        $this->assertEqualsWithDelta(1.2, Calculator::getOrderTaxFactor($standardRate), 0.0001);
        $this->assertEqualsWithDelta(
            1.055,
            Calculator::getOrderTaxFactor($reducedRate),
            0.0001,
            'The second order of the request must not inherit the first order tax factor.'
        );

        // 10 of discount on goods taxed at 5.5 % is 9.48 before tax, not 8.33.
        $this->assertEqualsWithDelta(
            10 / 1.055,
            Calculator::getUntaxedOrderDiscount($reducedRate),
            0.0001
        );
    }

    public function testEachCartKeepsItsOwnTaxFactor(): void
    {
        $country = CountryQuery::create()->findOne();
        $products = ProductQuery::create()->limit(2)->find();

        if ($country === null || \count($products) < 2) {
            $this->markTestSkipped('Needs a country and two products to build two carts.');
        }

        $standardRate = $this->createDiscountedCart($products[0], $this->createTaxRule($country, '20'));
        $reducedRate = $this->createDiscountedCart($products[1], $this->createTaxRule($country, '5.5'));

        $this->assertEqualsWithDelta(1.2, Calculator::getCartTaxFactor($standardRate, $country), 0.0001);
        $this->assertEqualsWithDelta(
            1.055,
            Calculator::getCartTaxFactor($reducedRate, $country),
            0.0001,
            'The second cart of the request must not inherit the first cart tax factor.'
        );

        $this->assertEqualsWithDelta(
            10 / 1.055,
            Calculator::getUntaxedCartDiscount($reducedRate, $country),
            0.0001
        );
    }

    /**
     * An order and its lines are never saved: the factor is read off the object graph.
     */
    private function createDiscountedOrder(string $taxAmount): Order
    {
        $orderProductTax = new OrderProductTax();
        $orderProductTax
            ->setTitle('VAT')
            ->setDescription('')
            ->setAmount($taxAmount);

        $orderProduct = new OrderProduct();
        $orderProduct
            ->setProductRef('taxed-ref')
            ->setProductSaleElementsRef('taxed-pse-ref')
            ->setTitle('Taxed product')
            ->setQuantity(1.0)
            ->setPrice('100.000000')
            ->addOrderProductTax($orderProductTax);

        $order = new Order();
        $order
            ->setDiscount('10.000000')
            ->addOrderProduct($orderProduct);

        return $order;
    }

    /**
     * The cart is not saved either. The product comes from the database because the
     * factor reads its tax rule, but the rule assigned here stays in memory.
     */
    private function createDiscountedCart($product, TaxRule $taxRule): Cart
    {
        $product->setTaxRule($taxRule);

        $cartItem = new CartItem();
        $cartItem
            ->setProduct($product)
            ->setQuantity(1)
            ->setPrice('100')
            ->setPromoPrice('100')
            ->setPromo(0);

        $cart = new Cart();
        $cart
            ->setDiscount('10.000000')
            ->addCartItem($cartItem);

        return $cart;
    }

    private function createTaxRule(Country $country, string $percent): TaxRule
    {
        $tax = new Tax();
        $tax
            ->setType('Thelia\TaxEngine\TaxType\PricePercentTaxType')
            ->setRequirements(['percent' => $percent])
            ->setLocale('en_US')
            ->setTitle('VAT '.$percent)
            ->setDescription('')
            ->save();

        $taxRule = new TaxRule();
        $taxRule
            ->setIsDefault(false)
            ->setLocale('en_US')
            ->setTitle('Tax rule '.$percent)
            ->setDescription('')
            ->save();

        $taxRuleCountry = new TaxRuleCountry();
        $taxRuleCountry
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save();

        array_push($this->createdRows, $tax, $taxRule, $taxRuleCountry);

        return $taxRule;
    }
}
