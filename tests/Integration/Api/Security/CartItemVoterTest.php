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

namespace Thelia\Tests\Integration\Api\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session as BaseSession;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Thelia\Api\Resource\CartItem;
use Thelia\Api\Security\CartItemVoter;
use Thelia\Api\Security\CartOwnership;
use Thelia\Api\Security\GuestTokenClaims;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\GuestToken;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\CartItem as CartItemModel;
use Thelia\Model\Customer;
use Thelia\Test\IntegrationTestCase;

/**
 * The voter is the second lock on the front cart item operations: the query
 * extension normally keeps a foreign row out of the query long before the
 * voter is asked, so its verdicts are pinned here rather than over HTTP.
 *
 * The kernel is booted for Propel's database map alone: the models below stay
 * in memory and nothing is written.
 */
final class CartItemVoterTest extends IntegrationTestCase
{
    public function testTheOwningCustomerIsGranted(): void
    {
        $customer = $this->customer(42);
        $cartItem = $this->cartItemInCart(cartId: 7, cartCustomerId: 42);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($cartItem, customer: $customer),
        );
    }

    public function testAnotherCustomerIsDenied(): void
    {
        $customer = $this->customer(42);
        $cartItem = $this->cartItemInCart(cartId: 7, cartCustomerId: 43);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($cartItem, customer: $customer),
        );
    }

    public function testAnAnonymousCallerIsDeniedACustomerCart(): void
    {
        $cartItem = $this->cartItemInCart(cartId: 7, cartCustomerId: 42);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($cartItem),
        );
    }

    /**
     * A visitor builds a cart before signing in: the session that opened the
     * cart owns it, and must keep reading it.
     */
    public function testTheVisitorSessionOwnsTheCartItOpened(): void
    {
        $cartItem = $this->cartItemInCart(cartId: 7, cartCustomerId: null);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($cartItem, sessionCartId: 7),
        );
    }

    public function testAVisitorSessionIsDeniedAnotherVisitorsCart(): void
    {
        $cartItem = $this->cartItemInCart(cartId: 7, cartCustomerId: null);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($cartItem, sessionCartId: 8),
        );
    }

    /**
     * Nothing anchors the resource to a row, so there is nothing to own.
     */
    public function testAResourceWithoutItsModelIsDenied(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote(new CartItem(), sessionCartId: 7),
        );
    }

    /**
     * A guest holds the very customer row its token names, so the customer id says
     * yes to every cart on that row — including the carts of everyone who checked out
     * as a guest from the same address before. Only the cart the token was issued for
     * belongs to this caller.
     */
    public function testAGuestIsGrantedTheCartItsTokenNames(): void
    {
        $guest = $this->customer(42);
        $cartItem = $this->cartItemInCart(cartId: 7, cartCustomerId: 42);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($cartItem, customer: $guest, guestCartId: 7),
        );
    }

    public function testAGuestIsDeniedAnotherCartOnTheAccountItShares(): void
    {
        $guest = $this->customer(42);
        $cartItem = $this->cartItemInCart(cartId: 7, cartCustomerId: 42);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($cartItem, customer: $guest, guestCartId: 8),
        );
    }

    public function testAGuestTokenNamingNoCartOwnsNothing(): void
    {
        $guest = $this->customer(42);
        $cartItem = $this->cartItemInCart(cartId: 7, cartCustomerId: 42);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($cartItem, customer: $guest, guestCartId: null, asGuest: true),
        );
    }

    /**
     * The session a guest is checking out in still holds the cart, so a guest token
     * must not be widened by it either.
     */
    public function testAGuestIsDeniedTheCartOfTheSessionItIsNotScopedTo(): void
    {
        $guest = $this->customer(42);
        $cartItem = $this->cartItemInCart(cartId: 7, cartCustomerId: null);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($cartItem, customer: $guest, sessionCartId: 7, guestCartId: 8),
        );
    }

    private function vote(
        CartItem $subject,
        ?Customer $customer = null,
        ?int $sessionCartId = null,
        ?int $guestCartId = null,
        bool $asGuest = false,
    ): int {
        $isGuest = $asGuest || null !== $guestCartId;
        $token = $this->token($customer, $isGuest, $guestCartId);
        $tokenStorage = new TokenStorage();

        if (null !== $customer) {
            $tokenStorage->setToken($token);
        }

        $voter = new CartItemVoter(
            new CartOwnership(
                $tokenStorage,
                $this->requestStack($sessionCartId),
                new GuestTokenClaims($tokenStorage),
            ),
        );

        return $voter->vote($token, $subject, [CartItemVoter::OWNER]);
    }

    private function customer(int $id): Customer
    {
        $customer = new Customer();
        $customer->setId($id);

        return $customer;
    }

    private function cartItemInCart(int $cartId, ?int $cartCustomerId): CartItem
    {
        $cart = new CartModel();
        $cart->setId($cartId);

        if (null !== $cartCustomerId) {
            $cart->setCustomerId($cartCustomerId);
        }

        $model = new CartItemModel();
        $model->setCart($cart);

        return (new CartItem())->setPropelModel($model);
    }

    private function token(?Customer $customer, bool $asGuest = false, ?int $guestCartId = null): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($customer);
        $token->method('getRoleNames')->willReturn($asGuest ? [GuestToken::ROLE] : ['ROLE_CUSTOMER']);
        $token->method('hasAttribute')->willReturnCallback(
            static fn (string $name): bool => $asGuest && GuestToken::CART_TOKEN_ATTRIBUTE === $name,
        );
        $token->method('getAttribute')->willReturnCallback(
            static fn (string $name): mixed => GuestToken::CART_TOKEN_ATTRIBUTE === $name ? $guestCartId : null,
        );

        return $token;
    }

    private function requestStack(?int $sessionCartId): RequestStack
    {
        $stack = new RequestStack();
        $request = Request::create('http://localhost/api/front/cart_items');

        if (null !== $sessionCartId) {
            $session = new BaseSession(new MockArraySessionStorage());
            $session->start();
            $session->set(Session::SESSION_CART_ID_NAME, $sessionCartId);
            $request->setSession($session);
        }

        $stack->push($request);

        return $stack;
    }
}
