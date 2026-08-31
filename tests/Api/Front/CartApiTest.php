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

use Thelia\Test\ApiTestCase;

/**
 * Tests for the front cart and checkout API endpoints.
 *
 * The cart is session-based. Without prior cart creation via POST,
 * GET /api/front/cart returns 404 (no active cart). Delivery/payment
 * module endpoints depend on module-specific tables that may not be
 * seeded in the test DB.
 */
final class CartApiTest extends ApiTestCase
{
    public function testGetCartReturns404WithoutActiveCart(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/cart');

        // No cart in session → 404 is the expected behavior.
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * Reading a cart estimates its postage, and the estimate used to be read
     * as an array although the estimator answers with an object, which made
     * every single cart read a 500.
     */
    public function testCustomerCanReadOwnCart(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/carts/'.$cart->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($cart->getId(), $data['id']);
    }

    /**
     * `cart.address_delivery_id` and `cart.address_invoice_id` are foreign keys
     * on `cart_address`, so the two fields the cart exposes must answer the
     * cart's own copy of the address — the copy the order is built from.
     */
    public function testCartExposesTheAddressesItPointsAt(): void
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $customer = $factory->customer($title, ['password' => 'password']);
        $country = $factory->country();
        $address = $factory->address($customer, $country, $title);

        $deliveryAddress = $factory->cartAddress($address, $country, $title, ['city' => 'Bordeaux']);
        $invoiceAddress = $factory->cartAddress($address, $country, $title, ['city' => 'Toulouse']);

        $cart = $factory->cart($customer);
        $cart
            ->setAddressDeliveryId($deliveryAddress->getId())
            ->setAddressInvoiceId($invoiceAddress->getId())
            ->save($this->getPropelConnection());

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/carts/'.$cart->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);

        self::assertIsArray($data['addressDelivery'], 'The delivery address must carry data, not null.');
        self::assertSame($deliveryAddress->getId(), $data['addressDelivery']['id']);
        self::assertSame('Bordeaux', $data['addressDelivery']['city']);

        self::assertIsArray($data['addressInvoice'], 'The invoice address must carry data, not null.');
        self::assertSame($invoiceAddress->getId(), $data['addressInvoice']['id']);
        self::assertSame('Toulouse', $data['addressInvoice']['city']);

        // The copy keeps a link to the account address it was made from.
        self::assertSame($address->getId(), $data['addressDelivery']['address']['id']);
    }

    /**
     * An address typed in at checkout has no row in `address`, so resolving the
     * cart's address through the account would answer null in exactly the case
     * that matters most.
     */
    public function testCartExposesAnAddressTypedAtCheckout(): void
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $customer = $factory->customer($title, ['password' => 'password']);

        $typedAddress = $factory->cartAddress(null, $factory->country(), $title, ['city' => 'Lille']);

        $cart = $factory->cart($customer);
        $cart
            ->setAddressDeliveryId($typedAddress->getId())
            ->save($this->getPropelConnection());

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/carts/'.$cart->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);

        self::assertIsArray($data['addressDelivery']);
        self::assertSame('Lille', $data['addressDelivery']['city']);

        // Null values are dropped from the payload, so the account address the
        // copy was never made from is simply absent.
        self::assertArrayNotHasKey(
            'address',
            $data['addressDelivery'],
            'An address typed at checkout has no account address.',
        );
        self::assertArrayNotHasKey('addressInvoice', $data);
    }

    public function testCreateCartViaPost(): void
    {
        $response = $this->jsonRequest('POST', '/api/front/carts', []);

        // Cart creation should succeed (201) or return validation error.
        $statusCode = $response->getStatusCode();
        self::assertContains($statusCode, [200, 201, 400, 422]);
    }

    public function testPaymentModulesCollectionReturns200(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/payment/modules');

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('hydra:member', $data);
    }

    public function testCartItemsCollectionReturns200(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/cart_items');

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('hydra:member', $data);
    }

    public function testCouponsCollectionReturns200ForAnAuthenticatedCustomer(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/coupons', token: $this->authenticateAsCustomer());

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('hydra:member', $data);
    }
}
