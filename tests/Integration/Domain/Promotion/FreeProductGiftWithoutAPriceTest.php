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

namespace Thelia\Tests\Integration\Domain\Promotion;

use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\FreeProduct;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\ProductPriceQuery;

/**
 * A gift nothing prices (no price in the default currency) cannot be put in the cart: the
 * cart writes no line for it. The coupon then offers nothing and the customer's own line
 * stays, instead of reading the id of a line that does not exist.
 */
final class FreeProductGiftWithoutAPriceTest extends ActionIntegrationPromotionTestCase
{
    public function testAGiftNothingPricesIsNotOfferedAndDoesNotBreakThePromotion(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 7.0]);
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->defaultPseFor($gift)->getId())->delete();

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger);

        /** @var FacadeInterface $facade */
        $facade = $this->getService('thelia.facade');
        $coupon = new FreeProduct($facade);
        $coupon->set(
            $facade,
            'NO-PRICE-GIFT',
            'Free gift',
            '',
            '',
            [
                FreeProduct::PRODUCTS_LIST => [$trigger->getId()],
                FreeProduct::CATEGORY_ID => 0,
                FreeProduct::OFFERED_PRODUCT_ID => $gift->getId(),
                FreeProduct::OFFERED_CATEGORY_ID => 0,
            ],
            true,
            false,
            false,
            true,
            -1,
            new \DateTime('+1 month'),
            [],
            [],
            false,
        );

        self::assertSame(0.0, $coupon->exec());

        CartItemTableMap::clearInstancePool();
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($gift->getId())->count(), 'The gift nothing prices is not offered.');
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($trigger->getId())->count(), 'The customer line stays.');
    }
}
