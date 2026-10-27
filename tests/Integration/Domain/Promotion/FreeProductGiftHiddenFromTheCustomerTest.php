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

/**
 * A gift the catalog hides from the customer (a category their group is not open to) is not
 * offered: the cart refuses the product, the coupon offers nothing and the customer's own line
 * stays, instead of the promotion breaking the cart.
 */
final class FreeProductGiftHiddenFromTheCustomerTest extends ActionIntegrationPromotionTestCase
{
    public function testAGiftOfAClosedCategoryIsNotOfferedAndDoesNotBreakThePromotion(): void
    {
        $sofas = $this->factory->category();
        $diningRoom = $this->factory->category();
        $trigger = $this->productIn($sofas);
        $gift = $this->productIn($diningRoom, ['basePrice' => 7.0]);
        $pro = $this->factory->customerGroup('pro', categoryIds: [(int) $sofas->getId()]);
        $this->session()->setCustomerUser($this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]));
        $this->getService('services_resetter')->reset();

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger);

        /** @var FacadeInterface $facade */
        $facade = $this->getService('thelia.facade');
        $coupon = new FreeProduct($facade);
        $coupon->set(
            $facade,
            'HIDDEN-GIFT',
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

        try {
            self::assertSame(0.0, $coupon->exec());

            CartItemTableMap::clearInstancePool();
            self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($gift->getId())->count(), 'The gift the customer does not see is not offered.');
            self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($trigger->getId())->count(), 'The customer line stays.');
        } finally {
            $this->session()->setCustomerUser(null);
            $this->getService('services_resetter')->reset();
        }
    }
}
