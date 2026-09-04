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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Model\OrderStatus;
use Thelia\Tests\Support\Flexy\GuestOrderTokenInjector;

/**
 * How long the way back to a guest order stays in the session.
 *
 * A browser is not a person. The token of an order placed without an account opens that
 * order to whoever holds the session, and the session outlives the buyer: someone else
 * signing in on the same browser, or the buyer signing out and handing it over, must not
 * find the previous order still on the confirmation page.
 */
final class GuestOrderTokenLifetimeTest extends GuestCheckoutTestCase
{
    private GuestOrderTokenInjector $tokenInjector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenInjector = new GuestOrderTokenInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->tokenInjector);
    }

    protected function tearDown(): void
    {
        $this->tokenInjector->clear();

        parent::tearDown();
    }

    public function testTheConfirmationOffersTheTrackingLinkOfTheOrderThisSessionPlaced(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $token = $this->stashTokenOfAGuestOrder();

        $crawler = $this->client->request('GET', '/checkout/confirm');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertGreaterThan(
            0,
            $crawler->filter(\sprintf('a[href="/order/track/%s"]', $token))->count(),
            'The buyer must be offered the link to the order they have just placed.',
        );
    }

    public function testTheTrackingLinkIsGoneFromTheConfirmationOnceSomebodySignsIn(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $this->stashTokenOfAGuestOrder();
        $this->signInAsARealAccount();

        $crawler = $this->client->request('GET', '/checkout/confirm');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(
            0,
            $crawler->filter('a[href^="/order/track/"]'),
            'Whoever signed in did not place that order, and must not be handed the way into it.',
        );
    }

    public function testTheTrackingLinkIsGoneFromTheConfirmationOnceTheSessionIsGivenUp(): void
    {
        $this->skipUnlessTheThemeHasTheTrackingPage();

        $this->stashTokenOfAGuestOrder();
        $this->signInAsARealAccount();

        $this->client->request('GET', '/customer/logout');
        $this->client->request('GET', '/checkout/confirm');

        self::assertCount(
            0,
            $this->client->getCrawler()->filter('a[href^="/order/track/"]'),
            'The next person on this browser inherits neither the account nor the order.',
        );
    }

    /**
     * Puts the tracking token of a placed guest order in the session, exactly as the
     * checkout does when an order is placed without an account — and, like the checkout,
     * writes it once and never again.
     */
    private function stashTokenOfAGuestOrder(): string
    {
        $fixtures = $this->fixtures();
        $order = $fixtures->order(
            $fixtures->guestCustomer($fixtures->customerTitle()),
            ['statusCode' => OrderStatus::CODE_NOT_PAID],
        );

        $token = $this->getService(GuestOrderAccessService::class)->createToken($order);
        $this->tokenInjector->setToken($token);

        // The request that actually writes it: the injector forgets it right after, so
        // everything from here on reads the session and nothing else.
        $this->client->request('GET', '/');

        return $token;
    }
}
