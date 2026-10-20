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

namespace Thelia\Tests\Integration\Domain\Promotion\Coupon\Service;

use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Cart\Service\CartSelectionService;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Exception\DestinationRestrictedException;
use Thelia\Domain\Promotion\Coupon\OfferedLine\OfferedLineProviderInterface;
use Thelia\Domain\Promotion\Coupon\OfferedLine\OfferedLineRequest;
use Thelia\Domain\Promotion\Coupon\Service\OfferedCartLineService;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionChecker;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionFacade;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\DestinationRestriction;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;

/**
 * A promotion's gift is written to the cart with direct Propel writes, never through
 * CART_ADDITEM, and the buyer cannot remove an offered line. A gift the destination
 * of the cart refuses is therefore not offered: otherwise the placement guard would
 * refuse the cart for a line nobody can take out of it.
 */
final class OfferedGiftDestinationRestrictionTest extends IntegrationTestCase
{
    public function testTheCartIsNotRefusedForAGiftThePromotionKeepsPuttingBack(): void
    {
        $factory = $this->createFixtureFactory();
        $this->getService(DestinationRestrictionChecker::class)->reset();
        $this->getService(OfferedCartLineService::class)->reset();
        $refused = CountryQuery::create()->filterByHasStates(0)->orderById()->findOne();
        self::assertNotNull($refused);

        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $bought = $factory->product($category, $taxRule, $currency, ['title' => 'Glass', 'baseQuantity' => 50]);
        $gift = $factory->product($category, $taxRule, $currency, ['title' => 'Miniature of absinthe', 'baseQuantity' => 50]);

        $facade = $this->getService(DestinationRestrictionFacade::class);
        $group = $facade->save(null, 'v6-gift', DestinationRestriction::MODE_DENY, true, ['en_US' => ['title' => 'Absinthe', 'reason' => 'Absinthe ban']]);
        $facade->addDestination((int) $group->getId(), (int) $refused->getId());
        $facade->attachProduct((int) $group->getId(), (int) $gift->getId());

        $customer = $factory->customer($factory->customerTitle());
        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $bought);
        $carrier = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->orderById()->findOne();
        self::assertNotNull($carrier);
        $cart->setAddressDeliveryId($factory->cartAddress(null, $refused)->getId())->setDeliveryModuleId($carrier->getId())->save();

        $promotion = $factory->coupon();
        $offer = $this->createStubForIntersectionOfInterfaces([CouponInterface::class, OfferedLineProviderInterface::class]);
        $offer->method('getOfferedLineRequests')->willReturn([new OfferedLineRequest((int) $gift->getId(), 1, (int) $promotion->getId())]);
        $offer->method('getTitle')->willReturn('Free miniature');
        $offer->method('getCode')->willReturn('FREE-MINI');

        $this->getService(OfferedCartLineService::class)->reconcile([$offer], $cart);
        $cart->clearCartItems();

        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->filterByIsOffered(1)->count(), 'The gift the destination refuses is not offered.');

        try {
            $this->getService(CartGuard::class)->checkValidDelivery($cart);
        } catch (DestinationRestrictedException $refusal) {
            self::assertNotContains(
                (int) $gift->getId(),
                $refusal->violationDetails()['productIds'],
                'The cart is refused for the gift the promotion keeps adding back: '.$refusal->getMessage(),
            );
        } catch (CheckoutException) {
            // Whatever else the carrier says about this cart is not the point here.
        }

        self::assertTrue(true);
    }

    public function testADeliveryAddressRefusedOnlyByTheOfferedGiftIsAccepted(): void
    {
        $factory = $this->createFixtureFactory();
        $this->getService(DestinationRestrictionChecker::class)->reset();
        $this->getService(OfferedCartLineService::class)->reset();
        [$refused, $served] = CountryQuery::create()->filterByHasStates(0)->orderById()->limit(2)->find()->getData();

        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $bought = $factory->product($category, $taxRule, $currency, ['title' => 'Glass', 'baseQuantity' => 50]);
        $gift = $factory->product($category, $taxRule, $currency, ['title' => 'Miniature of absinthe', 'baseQuantity' => 50]);

        $facade = $this->getService(DestinationRestrictionFacade::class);
        $group = $facade->save(null, 'v6-gift-address', DestinationRestriction::MODE_DENY, true, ['en_US' => ['title' => 'Absinthe', 'reason' => 'Absinthe ban']]);
        $facade->addDestination((int) $group->getId(), (int) $refused->getId());
        $facade->attachProduct((int) $group->getId(), (int) $gift->getId());

        $customer = $factory->customer($factory->customerTitle());
        $factory->address($customer, $served)->setIsDefault(1)->save();
        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $bought);

        $promotion = $factory->coupon();
        $offer = $this->createStubForIntersectionOfInterfaces([CouponInterface::class, OfferedLineProviderInterface::class]);
        $offer->method('getOfferedLineRequests')->willReturn([new OfferedLineRequest((int) $gift->getId(), 1, (int) $promotion->getId())]);
        $offer->method('getTitle')->willReturn('Free miniature');
        $offer->method('getCode')->willReturn('FREE-MINI');

        // No address chosen yet: the default address of the customer is served, the gift is offered.
        $this->getService(OfferedCartLineService::class)->reconcile([$offer], $cart);
        $cart->clearCartItems();
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->filterByIsOffered(1)->count(), 'Precondition: the gift is offered on the default address.');

        $abroad = $factory->address($customer, $refused);

        try {
            $this->getService(CartSelectionService::class)->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: $abroad->getId()));
        } catch (DestinationRestrictedException $refusal) {
            self::assertNotContains(
                (int) $gift->getId(),
                $refusal->violationDetails()['productIds'],
                'The address is refused for the gift the buyer cannot remove: '.$refusal->getMessage(),
            );
        }

        self::assertTrue(true);
    }
}
