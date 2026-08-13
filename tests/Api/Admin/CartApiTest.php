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

namespace Thelia\Tests\Api\Admin;

use Thelia\Test\ApiTestCase;

final class CartApiTest extends ApiTestCase
{
    /**
     * `cart.address_delivery_id` is a foreign key on `cart_address`, so the
     * admin cart must answer the cart's own copy of the address rather than
     * the null it used to answer through a relation the model does not have.
     */
    public function testAdminCartExposesTheAddressesItPointsAt(): void
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $customer = $factory->customer($title);
        $country = $factory->country();

        $deliveryAddress = $factory->cartAddress(
            $factory->address($customer, $country, $title),
            $country,
            $title,
            ['city' => 'Nantes'],
        );

        $cart = $factory->cart($customer);
        $cart
            ->setAddressDeliveryId($deliveryAddress->getId())
            ->setAddressInvoiceId($deliveryAddress->getId())
            ->save($this->getPropelConnection());

        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('GET', '/api/admin/carts/'.$cart->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);

        self::assertIsArray($data['addressDelivery']);
        self::assertSame($deliveryAddress->getId(), $data['addressDelivery']['id']);
        self::assertSame('Nantes', $data['addressDelivery']['city']);
        self::assertSame($country->getId(), $data['addressDelivery']['country']['id']);

        self::assertIsArray($data['addressInvoice']);
        self::assertSame($deliveryAddress->getId(), $data['addressInvoice']['id']);
    }
}
