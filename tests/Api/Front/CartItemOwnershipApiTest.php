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

use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * A cart item belongs to a cart, and a cart belongs to a customer or to the
 * visitor session that opened it. The front endpoints must therefore answer
 * only for the cart the caller owns: a numeric, sequential cart item id must
 * never be enough to read, change or drop somebody else's cart line.
 */
final class CartItemOwnershipApiTest extends ApiTestCase
{
    public function testAnonymousCallerCannotReadSomebodyElsesCartItem(): void
    {
        [, , $cartItem] = $this->cartItemOwnedByACustomer();

        $response = $this->jsonRequest('GET', '/api/front/cart_items/'.$cartItem->getId());

        self::assertContains(
            $response->getStatusCode(),
            [401, 403, 404],
            'An anonymous caller must not read a cart item that belongs to a customer.',
        );
    }

    public function testAnonymousCallerCannotListSomebodyElsesCartItems(): void
    {
        [, , $cartItem] = $this->cartItemOwnedByACustomer();

        $response = $this->jsonRequest('GET', '/api/front/cart_items');

        if (200 !== $response->getStatusCode()) {
            self::assertContains($response->getStatusCode(), [401, 403]);

            return;
        }

        self::assertNotContains(
            $cartItem->getId(),
            $this->collectionIds($response->getContent()),
            'A cart item owned by a customer must not surface in an anonymous collection.',
        );
    }

    public function testAnotherCustomerCannotReadACartItem(): void
    {
        $factory = $this->createFixtureFactory();
        [, , $cartItem] = $this->cartItemOwnedByACustomer($factory);

        $intruder = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $token = $this->authenticateAsCustomer($intruder);

        $response = $this->jsonRequest('GET', '/api/front/cart_items/'.$cartItem->getId(), token: $token);

        self::assertContains(
            $response->getStatusCode(),
            [401, 403, 404],
            'A customer must not read a cart item that belongs to another customer.',
        );
    }

    public function testAnonymousCallerCannotChangeSomebodyElsesCartItem(): void
    {
        [, , $cartItem] = $this->cartItemOwnedByACustomer();
        $quantityBefore = $cartItem->getQuantity();

        $response = $this->jsonRequest(
            'PUT',
            '/api/front/cart_items/'.$cartItem->getId(),
            ['quantity' => 99],
        );

        self::assertContains(
            $response->getStatusCode(),
            [401, 403, 404],
            'An anonymous caller must not change a cart item that belongs to a customer.',
        );

        self::assertSame(
            $quantityBefore,
            $this->reloadQuantity($cartItem->getId()),
            'The quantity of a foreign cart item must be left untouched.',
        );
    }

    public function testAnonymousCallerCannotDeleteSomebodyElsesCartItem(): void
    {
        [, , $cartItem] = $this->cartItemOwnedByACustomer();

        $response = $this->jsonRequest('DELETE', '/api/front/cart_items/'.$cartItem->getId());

        self::assertContains(
            $response->getStatusCode(),
            [401, 403, 404],
            'An anonymous caller must not delete a cart item that belongs to a customer.',
        );

        self::assertSame(
            1,
            CartItemQuery::create()
                ->filterById($cartItem->getId())
                ->count($this->getPropelConnection()),
            'A foreign cart item must survive an unauthenticated DELETE.',
        );
    }

    /**
     * The owner keeps the access the fix restricts: the whole point is to
     * scope the endpoints, not to close them.
     */
    public function testTheOwningCustomerStillReadsTheirOwnCartItem(): void
    {
        $factory = $this->createFixtureFactory();
        [$customer, , $cartItem] = $this->cartItemOwnedByACustomer($factory);

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/cart_items/'.$cartItem->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($cartItem->getId(), $data['id']);
    }

    public function testTheOwningCustomerStillListsTheirOwnCartItems(): void
    {
        $factory = $this->createFixtureFactory();
        [$customer, , $cartItem] = $this->cartItemOwnedByACustomer($factory);

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/cart_items', token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertContains(
            $cartItem->getId(),
            $this->collectionIds($response->getContent()),
            'The owning customer must still see their own cart line.',
        );
    }

    /**
     * The scoping is a front rule. A back-office user legitimately reads every
     * cart, and the admin endpoints must keep answering for all of them.
     */
    public function testTheAdminEndpointStillReadsAnyCartItem(): void
    {
        [, , $cartItem] = $this->cartItemOwnedByACustomer();

        $response = $this->jsonRequest(
            'GET',
            '/api/admin/cart_items/'.$cartItem->getId(),
            token: $this->authenticateAsAdmin(),
        );

        self::assertJsonResponseSuccessful($response);
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($cartItem->getId(), $data['id']);
    }

    /**
     * @return array{0: Customer, 1: Cart, 2: CartItem}
     */
    private function cartItemOwnedByACustomer(?FixtureFactory $factory = null): array
    {
        $factory ??= $this->createFixtureFactory();

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $cartItem = $factory->cartItem($cart, $product);

        return [$customer, $cart, $cartItem];
    }

    private function reloadQuantity(int $cartItemId): ?float
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()
            ->findPk($cartItemId, $this->getPropelConnection())
            ?->getQuantity();
    }

    /**
     * @return list<int>
     */
    private function collectionIds(string|false $content): array
    {
        $payload = json_decode((string) $content, true, flags: \JSON_THROW_ON_ERROR);

        return array_map(
            static fn (array $member): int => (int) $member['id'],
            $payload['hydra:member'] ?? [],
        );
    }
}
