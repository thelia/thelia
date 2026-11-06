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

use Propel\Runtime\Propel;
use Thelia\Core\Event\ProductSaleElement\StockReleasedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartSelectionService;
use Thelia\Domain\Catalog\Stock\StockReservationAnnouncer;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\Exception\DestinationRestrictedException;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\FreeProduct;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionChecker;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionFacade;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\DestinationRestriction;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * AC15: a gift offered by a promotion that the destination refuses is not offered, so it
 * never gets the delivery address refused. The gift of a "free product" coupon is put in
 * the cart while the default address of the customer is served; the customer then picks
 * a delivery address the gift cannot ship to.
 */
final class FreeProductGiftRefusesTheDeliveryAddressTest extends ActionIntegrationPromotionTestCase
{
    public function testAFreeProductGiftDoesNotGetTheDeliveryAddressRefused(): void
    {
        [$cart, $gift, $abroad] = $this->cartWithTheGiftOfferedOnTheServedDefaultAddress();
        $refusedProductIds = [];

        try {
            $this->getService(CartSelectionService::class)->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: $abroad->getId()));
        } catch (DestinationRestrictedException $refusal) {
            $refusedProductIds = $refusal->violationDetails()['productIds'];
        }

        self::assertNotContains((int) $gift->getId(), $refusedProductIds, 'The delivery address is refused because of the gift the promotion offers (AC15).');
    }

    public function testTheGiftIsWithdrawnOnceTheCartGoesWhereItCannotTravel(): void
    {
        [$cart, $gift, $abroad, $coupon] = $this->cartWithTheGiftOfferedOnTheServedDefaultAddress();

        $this->getService(CartSelectionService::class)->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: $abroad->getId()));

        self::assertSame(0.0, $coupon->exec(), 'A gift the destination refuses is not discounted.');
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($gift->getId())->count(), 'The gift the destination refuses is left in the cart, where it blocks the order (AC15).');
    }

    public function testAHeldGiftWithdrawnWithTheDestinationIsToldAsReleased(): void
    {
        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'cart');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();
        $callerTransaction = $this->getService(TestCallerTransaction::class);
        $callerTransaction->ignoreTheLevelsOpenOn(Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME));
        [$cart, $gift, $abroad, $coupon] = $this->cartWithTheGiftOfferedOnTheServedDefaultAddress();
        $giftLine = CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($gift->getId())->findOne();
        $giftLine->getProductSaleElements()->setQuantity(5)->save();
        $this->getService(StockReservationService::class)->holdAfterCartChange($cart);
        $held = $this->getService(StockReservationService::class)->heldLinesOf((int) $cart->getId(), (int) $giftLine->getId());
        self::assertCount(1, $held, 'Precondition: the gift line holds stock.');

        $told = [];
        $listener = static function (StockReleasedEvent $event) use (&$told): void {
            $told[] = [$event->getCartId(), $event->getLines()];
        };
        $this->dispatcher->addListener(TheliaEvents::STOCK_RELEASED, $listener);

        try {
            $this->getService(CartSelectionService::class)->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: $abroad->getId()));
            $coupon->exec();
            $this->getService(StockReservationAnnouncer::class)->flush();
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::STOCK_RELEASED, $listener);
            $callerTransaction->reset();
            ConfigQuery::resetCache();
        }

        self::assertEquals([[(int) $cart->getId(), $held]], $told);
    }

    /**
     * @return array{Cart, Product, Address, FreeProduct}
     */
    private function cartWithTheGiftOfferedOnTheServedDefaultAddress(): array
    {
        $checker = $this->getService(DestinationRestrictionChecker::class);
        $checker->reset();
        [$refused, $served] = CountryQuery::create()->filterByHasStates(0)->orderById()->limit(2)->find()->getData();

        $trigger = $this->product(['title' => 'Glass']);
        $gift = $this->product(['title' => 'Miniature of absinthe', 'basePrice' => 7.0]);

        $restrictions = $this->getService(DestinationRestrictionFacade::class);
        $group = $restrictions->save(null, 'free-product-gift-address', DestinationRestriction::MODE_DENY, true, ['en_US' => ['title' => 'Absinthe', 'reason' => 'Absinthe ban']]);
        $restrictions->addDestination((int) $group->getId(), (int) $refused->getId());
        $restrictions->attachProduct((int) $group->getId(), (int) $gift->getId());

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->address($customer, $served)->setIsDefault(1)->save();

        $this->session()->setCustomerUser($customer);
        $cart = $this->newEmptyCart();
        $cart->setCustomerId($customer->getId())->save();
        $this->addItem($cart, $trigger);

        /** @var FacadeInterface $facade */
        $facade = $this->getService('thelia.facade');
        $coupon = new FreeProduct($facade, $checker, $this->getService(StockReservationService::class));
        $coupon->set(
            $facade,
            'FREE-GIFT-ADDRESS',
            'Free miniature',
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

        // The default address of the customer is served: the gift is offered.
        $coupon->exec();
        CartItemTableMap::clearInstancePool();
        $cart->clearCartItems();
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($gift->getId())->count(), 'Precondition: the gift is offered on the served default address.');

        return [$cart, $gift, $this->factory->address($customer, $refused), $coupon];
    }
}
