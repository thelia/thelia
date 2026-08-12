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
use Thelia\Test\IntegrationTestCase;

/**
 * The tax factor spreads a cart discount over the VAT rates of what is being
 * bought. A discounted cart with nothing taxable in it has no rate to average,
 * and must be left with its discount as it is rather than divided by zero.
 */
final class CartTaxFactorWithoutTaxableItemTest extends IntegrationTestCase
{
    public function testACartWhoseItemsWereAllRemovedKeepsItsDiscount(): void
    {
        $country = $this->createFixtureFactory()->country();

        // A coupon was applied, then the last item was removed from the cart.
        $cart = $this->createDiscountedCart();

        self::assertCount(0, $cart->getCartItems());
        self::assertSame(1.0, Calculator::getCartTaxFactor($cart, $country));
        self::assertEqualsWithDelta(10.0, Calculator::getUntaxedCartDiscount($cart, $country), 0.0001);
    }

    public function testACartShippedWhereNoTaxRuleAppliesKeepsItsDiscount(): void
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country();

        // A tax rule of its own, with no tax declared for the delivery country.
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(['isDefault' => false]),
            $factory->currency(),
        );

        $cart = $this->createDiscountedCart();

        (new CartItem())
            ->setCart($cart)
            ->setProduct($product)
            ->setProductSaleElements($product->getDefaultSaleElements())
            ->setQuantity(1)
            ->setPrice('100')
            ->setPromoPrice('100')
            ->setPromo(0)
            ->save($this->getPropelConnection());

        self::assertSame(1.0, Calculator::getCartTaxFactor($cart, $country));
        self::assertEqualsWithDelta(10.0, Calculator::getUntaxedCartDiscount($cart, $country), 0.0001);
    }

    private function createDiscountedCart(): Cart
    {
        $cart = $this->createFixtureFactory()->cart();
        $cart->setDiscount('10.000000')->save($this->getPropelConnection());

        return $cart;
    }
}
