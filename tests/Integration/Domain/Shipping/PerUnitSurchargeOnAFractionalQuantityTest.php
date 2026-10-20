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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Thelia\Domain\Shipping\ProductRule\CartShippingRules;
use Thelia\Domain\Shipping\ProductRule\ShippingSurchargeMode;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * cart_item.quantity is a FLOAT (goods sold by weight carry a fractional quantity, see
 * Api\Resource\CartItem::$quantity). A surcharge charged per unit has to follow that
 * quantity, not its integer part.
 */
final class PerUnitSurchargeOnAFractionalQuantityTest extends IntegrationTestCase
{
    public function testAPerUnitSurchargeFollowsAFractionalQuantity(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency, ['baseQuantity' => 100]);
        $product
            ->setShippingSurcharge('10')
            ->setShippingSurchargeMode(ShippingSurchargeMode::PerUnit->value)
            ->setFreeShipping(0)
            ->save($this->getPropelConnection());

        $cart = (new Cart())
            ->setCurrencyId($currency->getId())
            ->setToken(uniqid('fractional-quantity-', true));
        $cart->save($this->getPropelConnection());

        $productSaleElements = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($productSaleElements);

        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($product->getId())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity(2.5)
            ->setPrice('30.00')
            ->setPromoPrice('30.00')
            ->setPromo(0)
            ->save($this->getPropelConnection());

        $cart->clearCartItems();

        self::assertEqualsWithDelta(2.5, (float) $cart->getCartItems()->getFirst()->getQuantity(), 0.0001);

        // 2.5 units at 10 per unit: 25, not 20.
        self::assertEqualsWithDelta(25.0, (new CartShippingRules())->for($cart)->untaxedSurcharge(), 0.0001);
    }
}
