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

use Symfony\Component\DomCrawler\Form;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;

/**
 * Turning the passwordless account an order was placed under into a real one.
 *
 * The account is kept rather than recreated — the order already hangs off it. It comes
 * out disabled, mailed an activation code, exactly like any other registration on this
 * shop: the buyer chose a password, not a session, and the link that led here stops
 * working anyway, because it is signed against a password hash that has just changed.
 * From then on the way in is the activation flow already used for a fresh registration.
 */
final class GuestAccountConversionTest extends GuestCheckoutTestCase
{
    private const CHOSEN_PASSWORD = 'a-brand-new-password-42';

    public function testTheTrackingPageOffersTheAccountToAGuestOrder(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder();
        $token = $this->tokenFor($order);

        $crawler = $this->client->request('GET', '/order/track/'.$token);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertGreaterThan(
            0,
            $crawler->filter(\sprintf('a[href="/order/track/%s/account"]', $token))->count(),
            'An order still hanging off a passwordless account must offer to complete it.',
        );
    }

    public function testTheOfferIsNotMadeForAnOrderOfARealAccount(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $fixtures = $this->fixtures();
        $order = $fixtures->order($fixtures->customer($fixtures->customerTitle()));

        $crawler = $this->client->request('GET', '/order/track/'.$this->tokenFor($order));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(
            0,
            $crawler->filter('a[href$="/account"]'),
            'There is nothing to complete on an account that already has an owner.',
        );
    }

    public function testChoosingAPasswordCompletesTheAccountWithoutSigningIn(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        // Left as the shop would have it for the fastest checkout — no address
        // confirmation required. The contract disables the converted account and mails
        // its activation code regardless of this setting, so this is the case most
        // likely to hide a regression back to the old "sign the buyer in" behaviour.
        $previousConfirmation = ConfigQuery::read('customer_email_confirmation', '0');
        ConfigQuery::write('customer_email_confirmation', '0');

        try {
            $guest = $this->guest();
            $order = $this->guestOrder($guest);

            $this->client->submit($this->accountFormFor($order));

            // Answered with a redirect rather than the page itself: the link that led
            // here is signed against the password hash that has just changed, so
            // refreshing the submission would replay it onto a dead token.
            self::assertSame(
                303,
                $this->client->getResponse()->getStatusCode(),
                'The conversion answers with a redirect, not with the page it was posted to.',
            );
            $this->assertResponseRedirectsTo('/customer/activation');

            $crawler = $this->client->followRedirect();

            self::assertSame(
                200,
                $this->client->getResponse()->getStatusCode(),
                'There is nothing to sign into yet: the buyer lands on the page that asks for the code.',
            );

            $this->forgetHydratedModels();
            $completed = CustomerQuery::create()->findPk($guest->getId());

            self::assertNotNull($completed);
            self::assertNotEmpty($completed->getPassword(), 'It now carries the password its owner chose.');
            self::assertTrue(
                $completed->isGuest(),
                'Still a guest row: choosing a password proves nothing until the code is answered.',
            );
            self::assertSame(
                $guest->getId(),
                $completed->getId(),
                'The account is kept, not recreated: the order already hangs off it.',
            );
            self::assertSame(
                0,
                $completed->getEnable(),
                'The converted account stays disabled until its owner activates it.',
            );

            self::assertStringContainsString(
                'activation code',
                $crawler->text(),
                'The buyer is told to look for the activation code rather than being sent to the account.',
            );
            self::assertGreaterThan(
                0,
                $crawler->filter('input[name*="activation_code"]')->count(),
                'The way in from here is the activation flow a fresh registration already uses.',
            );

            $this->client->request('GET', '/account');
            $this->assertResponseRedirectsTo('/customer/login');
        } finally {
            ConfigQuery::write('customer_email_confirmation', $previousConfirmation);
        }
    }

    public function testTheLinkStopsWorkingOnceTheAccountIsCompleted(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $order = $this->guestOrder();
        $token = $this->tokenFor($order);

        $this->client->submit($this->accountFormFor($order, $token));

        $this->client->restart();
        $this->forgetHydratedModels();
        $this->client->request('GET', '/order/track/'.$token);

        self::assertSame(
            404,
            $this->client->getResponse()->getStatusCode(),
            'A link signed against the old password hash must stop being accepted.',
        );
    }

    public function testTwoDifferentPasswordsAreRefused(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $guest = $this->guest();
        $order = $this->guestOrder($guest);

        $form = $this->accountFormFor($order);
        $form['flexybundle_form_guest_account_creation[password][second]'] = 'something-else-entirely';

        $this->client->submit($form);

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The form comes back rather than settling on one of the two.',
        );
        $this->forgetHydratedModels();
        self::assertTrue(
            CustomerQuery::create()->findPk($guest->getId())?->isGuest(),
            'Nothing may be written when the buyer has not confirmed the password.',
        );
    }

    private function accountFormFor(Order $order, ?string $token = null): Form
    {
        $token ??= $this->tokenFor($order);

        $crawler = $this->client->request('GET', '/order/track/'.$token.'/account');

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The page that asks for the password must be reachable from the link.',
        );

        $prefix = 'flexybundle_form_guest_account_creation';
        $form = $crawler->filter(\sprintf('form[name="%s"]', $prefix))->form();

        $form[$prefix.'[password][first]'] = self::CHOSEN_PASSWORD;
        $form[$prefix.'[password][second]'] = self::CHOSEN_PASSWORD;

        return $form;
    }

    private function tokenFor(Order $order): string
    {
        return $this->getService(GuestOrderAccessService::class)->createToken($order);
    }

    private function guest(): Customer
    {
        $fixtures = $this->fixtures();

        return $fixtures->guestCustomer($fixtures->customerTitle());
    }

    private function guestOrder(?Customer $guest = null): Order
    {
        return $this->fixtures()->order(
            $guest ?? $this->guest(),
            ['statusCode' => OrderStatus::CODE_NOT_PAID],
        );
    }
}
