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

use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ApiTestCase;

final class OrderApiTest extends ApiTestCase
{
    public function testGetOrderReturnsResource(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $order = $factory->order();

        $response = $this->jsonRequest('GET', '/api/admin/orders/'.$order->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($order->getId(), $data['id']);
        self::assertSame($order->getRef(), $data['ref']);
    }

    public function testListOrdersReturnsCollection(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $factory->order();

        $response = $this->jsonRequest('GET', '/api/admin/orders', token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertGreaterThanOrEqual(1, json_decode($response->getContent(), true)['hydra:totalItems']);
    }

    public function testPatchOrderWithoutRequiredFieldsReturns422(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $order = $factory->order();

        $paidStatus = OrderStatusQuery::create()->findOneByCode('paid');
        if (null === $paidStatus) {
            self::markTestSkipped('No "paid" order status in test DB');
        }

        // PATCH without orderProducts triggers validation error.
        // This documents the API contract: orders can't be partially
        // updated without providing the required orderProducts relation.
        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'orderStatus' => '/api/admin/order_statutes/'.$paidStatus->getId(),
        ], $token, 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode());
    }

    public function testGetOrderReturns404ForNonExistent(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('GET', '/api/admin/orders/999999', token: $token);

        self::assertSame(404, $response->getStatusCode());
    }
}
