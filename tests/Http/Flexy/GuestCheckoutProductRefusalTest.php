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

namespace Thelia\Tests\Http\Flexy;

use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\Cart;
use Thelia\Model\Map\CartTableMap;

/**
 * A cart holding a product the shop only sells to account holders.
 *
 * Two things are owed to the buyer here: being stopped where the cart stops being
 * orderable, rather than several steps later on the last click, and being told why.
 * A silent redirect to the sign-in page reads as the shop having changed its mind.
 */
final class GuestCheckoutProductRefusalTest extends GuestCheckoutTestCase
{
    private const REASON = 'requires an account';

    public function testTheVisitorIsToldWhyTheOrderCannotBePlacedWithoutAnAccount(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::EnabledUnlessProductForbids);
        $this->openASessionWithACart(guestCheckoutForbidden: true);

        $this->client->request('GET', '/checkout/delivery');

        $this->assertResponseRedirectsTo('/customer/login');
        $this->assertTheReasonIsOnThePageTheVisitorLandsOn();
    }

    /**
     * A shop that simply requires an account says nothing about products: the visitor is
     * sent to the sign-in page the way they always were.
     */
    public function testAShopThatRequiresAnAccountSaysNothingAboutProducts(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Disabled);
        $this->openASessionWithACart();

        $this->client->request('GET', '/checkout/delivery');

        $this->assertResponseRedirectsTo('/customer/login');

        $landed = $this->client->followRedirect();

        self::assertStringNotContainsString(self::REASON, $landed->text());
    }

    public function testTheDeliveryStepStopsAGuestWhoseCartGainedSuchAProduct(): void
    {
        $cart = $this->aGuestInTheCheckout();

        $this->addAForbiddenProductTo($cart);

        $this->client->request('GET', '/checkout/delivery');

        $this->assertResponseRedirectsTo('/customer/login');
        $this->assertTheReasonIsOnThePageTheVisitorLandsOn();
    }

    public function testThePaymentStepStopsAGuestWhoseCartGainedSuchAProduct(): void
    {
        $cart = $this->aGuestInTheCheckout();

        $this->addAForbiddenProductTo($cart);

        $this->client->request('GET', '/checkout/payment');

        $this->assertResponseRedirectsTo('/customer/login');
        $this->assertTheReasonIsOnThePageTheVisitorLandsOn();
    }

    private function aGuestInTheCheckout(): Cart
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::EnabledUnlessProductForbids);

        $cart = $this->openASessionWithACart();

        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));
        $this->assertResponseRedirectsTo('/checkout/delivery');

        return $cart;
    }

    private function addAForbiddenProductTo(Cart $cart): void
    {
        $fixtures = $this->fixtures();
        $product = $fixtures->product(
            $fixtures->category(),
            $fixtures->taxRule(),
            $fixtures->currency(),
            ['title' => 'A product that requires an account'],
        );
        $product->setGuestCheckoutForbidden(1)->save();

        $fixtures->cartItem($cart, $product);

        // The requests already served left the cart in the Propel instance pool with the
        // line collection they read: without this the checkout goes on seeing the cart as
        // it was before this product went into it.
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
    }

    private function assertTheReasonIsOnThePageTheVisitorLandsOn(): void
    {
        $landed = $this->client->followRedirect();

        self::assertStringContainsString(
            self::REASON,
            $landed->text(),
            'A buyer turned away has to read why, or they read it as the shop changing its mind.',
        );
    }
}
