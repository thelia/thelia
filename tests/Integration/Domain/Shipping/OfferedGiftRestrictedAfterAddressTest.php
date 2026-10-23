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

use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Exception\DestinationRestrictedException;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionChecker;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionFacade;
use Thelia\Model\CountryQuery;
use Thelia\Model\DestinationRestriction;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;

/**
 * A gift a promotion already put in the cart, whose product is restricted after the
 * delivery address was chosen: the buyer cannot remove an offered line, and the
 * promotion withdraws it on its own (OfferedCartLineService, at ORDER_PAY). The
 * placement guard must not refuse the cart because of it (AC15: a refused gift
 * "neither blocks the order"), the way the address step already leaves gifts out
 * (CartSelectionService::refuseARestrictedDestination).
 */
final class OfferedGiftRestrictedAfterAddressTest extends IntegrationTestCase
{
    public function testAnOfferedGiftRestrictedAfterTheAddressDoesNotBlockThePlacement(): void
    {
        $factory = $this->createFixtureFactory();
        $this->getService(DestinationRestrictionChecker::class)->reset();
        $country = CountryQuery::create()->filterByHasStates(0)->orderById()->findOne();
        self::assertNotNull($country);

        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $bought = $factory->product($category, $taxRule, $currency, ['title' => 'Glass', 'baseQuantity' => 50]);
        $gift = $factory->product($category, $taxRule, $currency, ['title' => 'Miniature of absinthe', 'baseQuantity' => 50]);
        $promotion = $factory->coupon();

        $customer = $factory->customer($factory->customerTitle());
        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $bought);
        $factory->cartItem($cart, $gift, null, ['isOffered' => 1, 'offeredByCouponId' => $promotion->getId(), 'price' => '0.000000', 'promoPrice' => '0.000000']);
        $carrier = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->orderById()->findOne();
        self::assertNotNull($carrier);
        $cart->setAddressDeliveryId($factory->cartAddress(null, $country)->getId())->setDeliveryModuleId($carrier->getId())->save();

        // The merchant restricts the gift once the address is chosen.
        $facade = $this->getService(DestinationRestrictionFacade::class);
        $group = $facade->save(null, 'v6-1-gift-after', DestinationRestriction::MODE_DENY, true, ['en_US' => ['title' => 'Absinthe', 'reason' => 'Absinthe ban']]);
        $facade->addDestination((int) $group->getId(), (int) $country->getId());
        $facade->attachProduct((int) $group->getId(), (int) $gift->getId());
        $cart->clearCartItems();

        $refusedProductIds = [];

        try {
            $this->getService(CartGuard::class)->checkValidDelivery($cart);
        } catch (DestinationRestrictedException $refusal) {
            $refusedProductIds = $refusal->violationDetails()['productIds'];
        } catch (CheckoutException) {
            // What the carrier says about this cart is not the point here.
        }

        self::assertNotContains(
            (int) $gift->getId(),
            $refusedProductIds,
            'The placement is refused for an offered gift the buyer cannot remove from the cart.',
        );
    }
}
