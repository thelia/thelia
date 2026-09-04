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

namespace Thelia\Tests\Api\Front;

use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\GuestToken;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Api\Trait\RegistersGuestCustomers;

/**
 * Opening a guest account over the API: what the shop lets through, and what the
 * visitor is handed when it does.
 */
final class GuestCustomerApiTest extends ApiTestCase
{
    use RegistersGuestCustomers;

    public function testRegisteringHandsBackATokenThatGrantsTheGuestRoleAndNothingElse(): void
    {
        $this->enableGuestCheckout();

        [$response, $body] = $this->registerGuest(['firstname' => 'Ada', 'lastname' => 'Lovelace']);

        self::assertJsonResponseSuccessful($response);
        self::assertNotEmpty($body['token'] ?? null, 'A guest registration must hand back the token it will check out with.');

        $payload = self::jwtPayload($body['token']);

        self::assertSame(
            [GuestToken::ROLE],
            $payload['roles'] ?? null,
            'The guest token must grant ROLE_GUEST alone: ROLE_CUSTOMER would open the account endpoints.',
        );
    }

    public function testTheTokenNamesTheCartItWasIssuedFor(): void
    {
        $this->enableGuestCheckout();

        [, $body] = $this->registerGuest();

        $payload = self::jwtPayload($body['token']);

        self::assertNotNull($body['cartId'] ?? null, 'The registration must answer which cart the guest is checking out.');
        self::assertSame(
            $body['cartId'],
            $payload[GuestToken::CART_CLAIM] ?? null,
            'The token must name the cart it was issued for, so it can be kept to that one cart.',
        );
    }

    public function testTheTokenExpiresWithinTheGuestLifetime(): void
    {
        $this->enableGuestCheckout();

        [, $body] = $this->registerGuest();

        $payload = self::jwtPayload($body['token']);
        $lifetime = $payload['exp'] - $payload['iat'];

        self::assertSame(GuestToken::DEFAULT_LIFETIME_IN_SECONDS, $body['expiresIn'] ?? null);
        self::assertLessThanOrEqual(
            GuestToken::DEFAULT_LIFETIME_IN_SECONDS,
            $lifetime,
            'A guest token is handed to a visitor the shop knows nothing about: it must not outlive a checkout.',
        );
    }

    public function testTheGuestAccountIsCreatedAsAGuestAndGetsTheVisitorCart(): void
    {
        $this->enableGuestCheckout();

        [, $body] = $this->registerGuest();

        $guest = CustomerQuery::create()->findPk($body['id'], $this->getPropelConnection());

        self::assertNotNull($guest);
        self::assertTrue($guest->isGuest(), 'The account opened by the guest checkout is a guest account.');

        $cart = CartQuery::create()->findPk($body['cartId'], $this->getPropelConnection());

        self::assertNotNull($cart);
        self::assertSame(
            $guest->getId(),
            $cart->getCustomerId(),
            'The cart the visitor was already holding must follow it into the guest account, the way it does at login.',
        );
    }

    /**
     * The api firewall is stateless and the token carries the identity, so nothing here
     * belongs in the HTTP session. A session customer written from this endpoint would
     * be an identity the API never reads and the front office would: one anonymous call
     * would have signed the caller's browser in as a guest, outside of any checkout.
     */
    public function testRegisteringDoesNotSignTheBrowserIn(): void
    {
        $this->enableGuestCheckout();

        $session = static::getContainer()->get(Session::class);
        $session->clearCustomerUser();

        [$response, $body] = $this->registerGuest();

        self::assertJsonResponseSuccessful($response);
        self::assertNull($session->getCustomerUser(), 'The API must leave the browser session as it found it.');
        self::assertFalse($session->isCustomerGuest());

        $cart = CartQuery::create()->findPk($body['cartId'], $this->getPropelConnection());

        self::assertNotNull($cart);
        self::assertSame(
            $body['id'],
            $cart->getCustomerId(),
            'The cart still follows the guest in the database — that is what the token names.',
        );
    }

    public function testTheBodyCannotHandTheGuestAPassword(): void
    {
        $this->enableGuestCheckout();

        [, $body] = $this->registerGuest(['password' => 'chosen-by-the-body']);

        $guest = CustomerQuery::create()->findPk($body['id'], $this->getPropelConnection());

        self::assertNotNull($guest);
        self::assertEmpty(
            $guest->getPassword(),
            'A guest has no password on purpose: a body that could set one would be opening an account nobody confirmed.',
        );

        $this->client->request(
            'POST',
            '/api/front/login',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['username' => $body['email'], 'password' => 'chosen-by-the-body'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            401,
            $this->client->getResponse()->getStatusCode(),
            'The password the body sent must not sign anyone in.',
        );
    }

    public function testTheShopCanRefuseGuestCheckoutOutright(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'disabled');

        [$response] = $this->registerGuest();

        self::assertSame(403, $response->getStatusCode(), 'A shop that requires an account must refuse to open a guest one.');
        self::assertSame(
            0,
            CustomerQuery::create()->filterByIsGuest(1)->filterByEmail('refused@test.com')->count($this->getPropelConnection()),
            'Nothing must be written when the shop refuses.',
        );
    }

    /**
     * The cart the visitor filled before saying who they are. It hangs off the session
     * and belongs to nobody yet, which is exactly the state the registration reads it in.
     */
    public function testACartHoldingAProductThatRequiresAnAccountIsRefused(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'enabled_unless_product_forbids');

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $product->setGuestCheckoutForbidden(1)->save($this->getPropelConnection());

        $cart = $factory->cart();
        $factory->cartItem($cart, $product);
        static::getContainer()->get(Session::class)->setSessionCart($cart);

        $email = 'forbidden-cart-'.bin2hex(random_bytes(6)).'@test.com';

        [$response] = $this->registerGuest(['email' => $email]);

        self::assertSame(
            422,
            $response->getStatusCode(),
            'A cart holding a product the shop marked as needing an account cannot be checked out as a guest.',
        );
        self::assertSame(
            0,
            CustomerQuery::create()->filterByEmail($email)->count($this->getPropelConnection()),
            'Nothing must be written for a checkout the shop is going to refuse.',
        );
    }

    public function testAnAddressThatAlreadyHasAnAccountIsRefused(): void
    {
        $this->enableGuestCheckout();

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        [$response] = $this->registerGuest(['email' => $customer->getEmail()]);

        self::assertSame(
            409,
            $response->getStatusCode(),
            'An address that belongs to a real account must be sent back to signing in, not turned into a guest.',
        );
    }

    public function testAnAddressIsRequiredAndMustLookLikeOne(): void
    {
        $this->enableGuestCheckout();

        $missing = $this->jsonRequest('POST', '/api/front/guest-customers', [
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
        ]);

        self::assertSame(422, $missing->getStatusCode(), 'A guest with no address could never be reached about its order.');

        $malformed = $this->jsonRequest('POST', '/api/front/guest-customers', [
            'email' => 'not-an-address',
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
        ]);

        self::assertSame(422, $malformed->getStatusCode(), 'The address the order confirmation goes to has to be one.');
    }

    public function testRegistrationsAreCappedForOneAddress(): void
    {
        $this->enableGuestCheckout();

        $email = 'flood-'.bin2hex(random_bytes(6)).'@test.com';
        $statuses = [];

        for ($attempt = 0; $attempt < 8; ++$attempt) {
            [$response] = $this->registerGuest(['email' => $email]);
            $statuses[] = $response->getStatusCode();
        }

        self::assertContains(
            429,
            $statuses,
            'Opening guest accounts takes no credential: a caller must not be able to keep going indefinitely.',
        );
    }
}
