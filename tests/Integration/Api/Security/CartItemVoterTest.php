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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Thelia\Api\Resource\CartItem;
use Thelia\Api\Security\CartItemVoter;
use Thelia\Api\Security\CartOwnership;
use Thelia\Core\HttpFoundation\Session\Session;
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

    private function vote(CartItem $subject, ?Customer $customer = null, ?int $sessionCartId = null): int
    {
        $voter = new CartItemVoter(
            new CartOwnership(
                $this->tokenStorage($customer),
                $this->requestStack($sessionCartId),
            ),
        );

        return $voter->vote($this->token($customer), $subject, [CartItemVoter::OWNER]);
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

    private function tokenStorage(?Customer $customer): TokenStorageInterface
    {
        $storage = new TokenStorage();

        if (null !== $customer) {
            $storage->setToken($this->token($customer));
        }

        return $storage;
    }

    private function token(?Customer $customer): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($customer);

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
