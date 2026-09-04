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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * Shared ground for the guest checkout tests: a shop setting that is put back as it was,
 * a session holding a cart, and a way to skip the whole file on a theme that predates the
 * identification page — the theme ships as its own package on its own release cycle.
 */
abstract class GuestCheckoutTestCase extends WebIntegrationTestCase
{
    protected const GUEST_EMAIL = 'guest-checkout-flow@test.com';

    protected const ACCOUNT_PASSWORD = 'a-password-of-a-real-account';

    private ?string $previousGuestCheckoutMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousGuestCheckoutMode = ConfigQuery::getGuestCheckoutMode();

        // The rate limiters guarding the guest checkout count per address and per client,
        // and every test here comes from the same client with the same address. Their
        // window outlives a test, so without this the fourth one is turned away for a
        // reason that has nothing to do with what it is checking.
        $container = static::getContainer();

        if ($container->has('cache.rate_limiter')) {
            $container->get('cache.rate_limiter')->clear();
        }
    }

    protected function tearDown(): void
    {
        // Written rather than rolled back with the transaction: ConfigQuery keeps the
        // value in a static cache that outlives the test, and the request handlers of
        // the next test read it from there.
        if (null !== $this->previousGuestCheckoutMode) {
            ConfigQuery::write('guest_checkout_mode', $this->previousGuestCheckoutMode);
        }

        parent::tearDown();
    }

    protected function setGuestCheckoutMode(GuestCheckoutMode $mode): void
    {
        ConfigQuery::write('guest_checkout_mode', $mode->value);
    }

    /**
     * Opens a real session holding a cart with one line in it.
     *
     * The cart is the one the shop itself created for this session — asking the cart page
     * for it, then filling it — rather than a fixture the session knows nothing about.
     */
    protected function openASessionWithACart(bool $guestCheckoutForbidden = false): Cart
    {
        $highestCartIdBefore = (int) CartQuery::create()->orderById(Criteria::DESC)->findOne()?->getId();

        $this->client->request('GET', '/checkout/cart');

        $cart = $this->cartOpenedAfter($highestCartIdBefore);

        $fixtures = $this->fixtures();
        $product = $fixtures->product(
            $fixtures->category(),
            $fixtures->taxRule(),
            $fixtures->currency(),
        );

        if ($guestCheckoutForbidden) {
            $product->setGuestCheckoutForbidden(1)->save();
        }

        $fixtures->cartItem($cart, $product);

        // The request that opened the cart left it in the Propel instance pool with an
        // empty line collection, and this process serves the next request too: without
        // this the checkout would read the cart as still empty.
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();

        return $cart;
    }

    /**
     * The cart the request that just ran opened for its session.
     *
     * Identified by being the one that did not exist before that request: the session
     * itself is gone by the time the test body runs — the kernel detaches it from the
     * request once the response is out — and the cart cookie only exists on a shop
     * configured to keep one.
     */
    private function cartOpenedAfter(int $highestCartIdBefore): Cart
    {
        $cart = CartQuery::create()
            ->filterById($highestCartIdBefore, Criteria::GREATER_THAN)
            ->orderById(Criteria::DESC)
            ->findOne()
        ;

        if (!$cart instanceof Cart) {
            self::fail('The cart page did not open a cart for this session.');
        }

        return $cart;
    }

    protected function requestIdentificationPage(): Crawler
    {
        $crawler = $this->client->request('GET', '/checkout/identify');

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The identification page must be served with a 200.',
        );

        return $crawler;
    }

    /**
     * The guest form, filled in with an address the shop can deliver to.
     *
     * @param array<string, string> $overrides
     */
    protected function guestFormOf(Crawler $crawler, array $overrides = []): Form
    {
        $prefix = 'flexybundle_form_guest_checkout';

        $form = $crawler->filter(\sprintf('form[name="%s"]', $prefix))->form();

        $values = [
            $prefix.'[title]' => (string) $this->firstCustomerTitleId(),
            $prefix.'[firstname]' => 'Jean',
            $prefix.'[lastname]' => 'Dupont',
            $prefix.'[email]' => self::GUEST_EMAIL,
            $prefix.'[address1]' => '12 rue de la Paix',
            $prefix.'[zipcode]' => '75002',
            $prefix.'[city]' => 'Paris',
            $prefix.'[country]' => (string) $this->deliverableCountryId(),
            $prefix.'[cellphone]' => '0600000000',
            $prefix.'[invoice_same]' => '1',
            $prefix.'[accept_privacy_policy]' => '1',
        ];

        foreach ([...$values, ...$overrides] as $field => $value) {
            if ($form->has($field)) {
                $form[$field] = $value;
            }
        }

        return $form;
    }

    protected function guestCustomerOf(string $email): ?Customer
    {
        return CustomerQuery::create()->filterByEmail($email)->findOne();
    }

    /**
     * Signs the session in hand into a real account, through the login form the theme
     * serves — credentials, form firewall token and all.
     *
     * Deliberately not a customer pushed into the session by a test helper: what is
     * being checked around this is what a session holding an account does, and the way
     * a session comes to hold one is this form.
     */
    protected function signInAsARealAccount(): Customer
    {
        $fixtures = $this->fixtures();
        $account = $fixtures->customer($fixtures->customerTitle(), ['password' => self::ACCOUNT_PASSWORD]);

        // Fixtures come out disabled, and the login of an unconfirmed account is sent to
        // the activation page instead of going through.
        $account->setEnable(1)->save($this->getPropelConnection());

        $crawler = $this->client->request('GET', '/customer/login');
        $form = $crawler->filter('form[name="thelia_customer_login"]')->form();

        $form['thelia_customer_login[email]'] = (string) $account->getEmail();
        $form['thelia_customer_login[password]'] = self::ACCOUNT_PASSWORD;
        $form['thelia_customer_login[account]'] = '1';

        $this->client->submit($form);

        $this->client->request('GET', '/account');

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The sign-in must go through, or whatever is checked around it proves nothing.',
        );

        return $account;
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when
     * the stack is empty, and it would then be the "main" request of every call below —
     * the one the session, and therefore the cart, is read from.
     */
    protected function fixtures(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }

    /**
     * Drops the models this process is holding on to.
     *
     * The whole suite runs in one process with the kernel kept alive, so a model a
     * request hydrated is handed back as-is to the next one — including a customer whose
     * password was erased in memory by the sign-in. A browser gets a fresh read every
     * time; this is how the test does too.
     */
    protected function forgetHydratedModels(): void
    {
        CustomerTableMap::clearInstancePool();
        CustomerTableMap::clearRelatedInstancePool();
        OrderTableMap::clearInstancePool();
        OrderTableMap::clearRelatedInstancePool();
    }

    protected function assertResponseRedirectsTo(string $path): void
    {
        $response = $this->client->getResponse();

        self::assertTrue($response->isRedirect(), 'The request must answer with a redirect.');
        self::assertStringEndsWith(
            $path,
            (string) $response->headers->get('Location'),
            \sprintf('The redirect must lead to "%s".', $path),
        );
    }

    /**
     * A theme older than the guest checkout declares none of these routes. Reported as a
     * skip rather than a failure: the core ships with whichever theme version it is given.
     */
    protected function skipUnlessTheThemeHasTheIdentificationPage(): void
    {
        $this->skipUnlessTheThemeHasRoute('checkout_identify');
    }

    protected function skipUnlessTheThemeHasTheTrackingPage(): void
    {
        $this->skipUnlessTheThemeHasRoute('guest_order_track');
    }

    private function skipUnlessTheThemeHasRoute(string $route): void
    {
        try {
            $this->getService(RouterInterface::class)->getRouteCollection()->get($route)
                ?? throw new RouteNotFoundException();
        } catch (RouteNotFoundException) {
            self::markTestSkipped(\sprintf('The installed theme declares no "%s" route.', $route));
        }
    }

    private function firstCustomerTitleId(): int
    {
        return (int) CustomerTitleQuery::create()->orderById()->findOne()?->getId();
    }

    /**
     * A country the delivery form accepts without a state: the shop the tests run against
     * is seeded with the full country list, and one that needs a state would make every
     * submission fail on a field this form only shows for such a country.
     */
    private function deliverableCountryId(): int
    {
        $country = CountryQuery::create()
            ->filterByHasStates(0)
            ->filterByNeedZipCode(0)
            ->orderById()
            ->findOne()
        ;

        return (int) ($country?->getId() ?? $this->fixtures()->country()->getId());
    }
}
