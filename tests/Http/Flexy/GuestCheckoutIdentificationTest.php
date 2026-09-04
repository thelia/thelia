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
use Thelia\Domain\Customer\CustomerFacade;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * What the checkout does with a visitor who has a cart and no session.
 *
 * The shop setting decides, and a shop that has not asked for the guest checkout must
 * see no change at all: the delivery step still sends such a visitor to the login page,
 * which is what it did before any of this existed.
 */
final class GuestCheckoutIdentificationTest extends GuestCheckoutTestCase
{
    public function testTheDeliveryStepStillSendsAVisitorToTheLoginPageWhenTheShopRequiresAnAccount(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Disabled);
        $this->openASessionWithACart();

        $this->client->request('GET', '/checkout/delivery');

        $this->assertResponseRedirectsTo('/customer/login');
    }

    public function testTheDeliveryStepSendsAVisitorToTheIdentificationPageWhenTheShopOffersTheGuestCheckout(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();

        $this->client->request('GET', '/checkout/delivery');

        $this->assertResponseRedirectsTo('/checkout/identify');
    }

    public function testTheIdentificationPageOffersToOrderWithoutAnAccountWhenTheShopAllowsIt(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();

        $crawler = $this->requestIdentificationPage();

        self::assertCount(
            1,
            $crawler->filter('form[name="flexybundle_form_guest_checkout"]'),
            'The page must carry the form that orders without an account.',
        );
    }

    public function testTheIdentificationPageDoesNotOfferToOrderWithoutAnAccountWhenTheShopRequiresOne(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Disabled);
        $this->openASessionWithACart();

        $crawler = $this->requestIdentificationPage();

        self::assertCount(
            0,
            $crawler->filter('form[name="flexybundle_form_guest_checkout"]'),
            'A shop that requires an account must not put the guest form on the page.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('form[name="thelia_customer_login"]')->count(),
            'The sign-in block stays, whatever the setting.',
        );
    }

    /**
     * The `enabled_unless_product_forbids` mode, seen from the page: one product in the
     * cart marked as needing an account is enough to take the offer away.
     */
    public function testACartHoldingAProductThatForbidsItIsNotOfferedTheGuestCheckout(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::EnabledUnlessProductForbids);
        $this->openASessionWithACart(guestCheckoutForbidden: true);

        $crawler = $this->requestIdentificationPage();

        self::assertCount(
            0,
            $crawler->filter('form[name="flexybundle_form_guest_checkout"]'),
            'A cart holding a product that requires an account must not offer to order without one.',
        );
    }

    public function testACartWithoutSuchAProductIsStillOfferedTheGuestCheckoutInThatMode(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::EnabledUnlessProductForbids);
        $this->openASessionWithACart();

        $crawler = $this->requestIdentificationPage();

        self::assertCount(
            1,
            $crawler->filter('form[name="flexybundle_form_guest_checkout"]'),
            'The mode only takes the offer away for the products the shop marked.',
        );
    }

    public function testTheSettingIsReadAgainWhenTheFormIsSubmitted(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();

        $form = $this->guestFormOf($this->requestIdentificationPage());

        // The shop closes the guest checkout between the page and its submission.
        $this->setGuestCheckoutMode(GuestCheckoutMode::Disabled);

        $this->client->submit($form);

        $this->assertResponseRedirectsTo('/customer/login');
        self::assertNull(
            $this->guestCustomerOf(self::GUEST_EMAIL),
            'A submission the shop no longer allows must not open an account.',
        );
    }

    /**
     * The form is served to visitors with no session at all, so a submission arriving
     * with one is a stale page or a forged post. Honouring it swapped a signed-in
     * customer for a passwordless row, on a POST that carries no credential.
     */
    public function testTheGuestFormIsRefusedToASessionThatAlreadyHoldsAnAccount(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();

        // The page as the visitor had it open before signing in somewhere else.
        $form = $this->guestFormOf($this->requestIdentificationPage());

        $account = $this->signInAsARealAccount();

        $this->client->submit($form);

        $this->assertResponseRedirectsTo('/checkout/delivery');
        self::assertNull(
            $this->guestCustomerOf(self::GUEST_EMAIL),
            'Nothing may be opened on behalf of a session that already knows who it holds.',
        );

        $this->client->request('GET', '/account');
        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            \sprintf('The account of %s must still be the one this session holds.', (string) $account->getEmail()),
        );
    }

    public function testTheIdentificationPageSendsAVisitorWithAnEmptyCartBackToIt(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        // A session with a cart, and nothing in it.
        $this->client->request('GET', '/checkout/cart');

        $this->client->request('GET', '/checkout/identify');

        $this->assertResponseRedirectsTo('/checkout/cart');
    }

    /**
     * `required` on a checkbox only marks it in the browser: an unticked box submits
     * nothing at all, and nothing is what a form ignores.
     */
    public function testTheFormIsRefusedWithoutTheConsentItAsksFor(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();

        $this->submitGuestFormWithoutTheConsentBox();

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The form comes back rather than placing an order under a consent nobody gave.',
        );
        self::assertNull(
            $this->guestCustomerOf(self::GUEST_EMAIL),
            'Nothing may be opened on a submission the form refuses.',
        );
    }

    /**
     * A buyer who chose a password on a guest order and never answered the code is still
     * a guest row: the login provider does not see it, so the login form can only tell
     * them their password is wrong. The activation page is their one way in, and typing
     * their address into the guest block is what points them at it.
     */
    public function testAnAddressAwaitingItsActivationCodeIsSentToTheActivationPage(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        $converted = $this->guestWhoChoseAPasswordAndNeverAnsweredTheCode();

        $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        $this->assertResponseRedirectsTo('/customer/activation');

        $crawler = $this->client->followRedirect();

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            'activation code',
            $crawler->text(),
            'The buyer has to be told what the page in front of them is waiting for.',
        );
        self::assertTrue(
            CustomerQuery::create()->findPk($converted->getId())?->isGuest(),
            'Nothing about the row changes: it is still waiting for its code.',
        );
    }

    private function guestWhoChoseAPasswordAndNeverAnsweredTheCode(): Customer
    {
        $fixtures = $this->fixtures();
        $guest = $fixtures->guestCustomer($fixtures->customerTitle(), ['email' => self::GUEST_EMAIL]);

        $this->getService(CustomerFacade::class)->convertGuestToCustomer($guest, self::ACCOUNT_PASSWORD);

        $this->forgetHydratedModels();

        return $guest;
    }

    /**
     * The consent box is a checkbox: unticking it takes the field out of the submission
     * altogether, which is what the browser does and what a `$form[...] = ''` would not.
     */
    private function submitGuestFormWithoutTheConsentBox(): void
    {
        $prefix = 'flexybundle_form_guest_checkout';
        $form = $this->guestFormOf($this->requestIdentificationPage());
        $values = $form->getPhpValues()[$prefix];

        unset($values['accept_privacy_policy']);

        $this->client->request('POST', $form->getUri(), [$prefix => $values]);
    }
}
