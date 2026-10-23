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

use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\FreeProduct;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionChecker;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionFacade;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\DestinationRestriction;
use Thelia\Model\Map\CartItemTableMap;

/**
 * A "free product" coupon puts its gift in the cart through CART_ADDITEM, which the
 * destination guard listens to. A gift the destination of the cart refuses must not
 * be offered (AC15), and the customer's own addition goes through, the way a gift out
 * of stock is handled (FreeProduct::exec catches NotEnoughStockException only).
 */
final class FreeProductGiftRestrictedByDestinationTest extends ActionIntegrationPromotionTestCase
{
    public function testAFreeProductGiftTheDestinationRefusesIsNotOfferedAndDoesNotRefuseTheCart(): void
    {
        $this->getService(DestinationRestrictionChecker::class)->reset();
        $country = CountryQuery::create()->filterByHasStates(0)->orderById()->findOne();
        self::assertNotNull($country);

        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 7.0]);

        $restrictions = $this->getService(DestinationRestrictionFacade::class);
        $group = $restrictions->save(null, 'v6-2-free-product', DestinationRestriction::MODE_DENY, true, ['en_US' => ['title' => 'Absinthe', 'reason' => 'Absinthe ban']]);
        $restrictions->addDestination((int) $group->getId(), (int) $country->getId());
        $restrictions->attachProduct((int) $group->getId(), (int) $gift->getId());

        $cart = $this->newEmptyCart();
        $cart->setAddressDeliveryId($this->factory->cartAddress(null, $country)->getId())->save();
        $this->addItem($cart, $trigger);

        /** @var FacadeInterface $facade */
        $facade = $this->getService('thelia.facade');
        $coupon = new FreeProduct($facade);
        $coupon->set(
            $facade,
            'V6-2-GIFT',
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

        $refusal = null;

        try {
            $coupon->exec();
        } catch (InvalidCartException $exception) {
            $refusal = $exception->getMessage();
        }

        self::assertNull($refusal, 'The promotion evaluation is refused because of the gift it offers: '.$refusal);

        CartItemTableMap::clearInstancePool();
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($gift->getId())->count(), 'The gift the destination refuses is not offered.');
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($trigger->getId())->count(), 'The customer line stays.');
    }
}
