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

use Propel\Runtime\Connection\ConnectionWrapper;
use Thelia\Domain\Order\Service\OrderStatusTransitionWriter;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ApiTestCase;

/**
 * A status written through the admin API is held to the same transition graph
 * as one chosen in the back office.
 */
final class OrderStatusTransitionApiTest extends ApiTestCase
{
    public function testAStatusTheGraphRefusesIsRejectedWithA422AndLeavesTheOrderUntouched(): void
    {
        $token = $this->authenticateAsAdmin();
        $order = $this->sentOrderWithOneLine();
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);

        $response = $this->jsonRequest('PUT', '/api/admin/orders/'.$order->getId(), $this->bodyWithStatus($token, $order->getId(), OrderStatus::CODE_NOT_PAID), $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('not allowed', (string) $response->getContent());
        self::assertSame(OrderStatus::CODE_SENT, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testAStatusTheGraphAllowsGoesThroughTheStatusChangeOfTheCore(): void
    {
        $token = $this->authenticateAsAdmin();
        $order = $this->sentOrderWithOneLine();
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);

        $response = $this->jsonRequest('PUT', '/api/admin/orders/'.$order->getId(), $this->bodyWithStatus($token, $order->getId(), OrderStatus::CODE_REFUNDED), $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(OrderStatus::CODE_REFUNDED, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testARefusedStatusRollsTheWholeRequestBack(): void
    {
        $token = $this->authenticateAsAdmin();
        $order = $this->sentOrderWithOneLine();
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);

        $body = $this->bodyWithStatus($token, $order->getId(), OrderStatus::CODE_NOT_PAID);
        $body['deliveryRef'] = 'PARCEL-REFUSED-WITH-STATUS';

        $response = $this->jsonRequest('PUT', '/api/admin/orders/'.$order->getId(), $body, $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        // The test itself wraps every request in a transaction, so the processor's
        // rollback is nested: Propel cannot undo the rows here and marks the outer
        // transaction as no longer committable instead. That mark is the proof that
        // the processor asked for the rollback of everything it wrote.
        $connection = $this->getPropelConnection();
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        self::assertTrue(
            (new \ReflectionProperty(ConnectionWrapper::class, 'isUncommitable'))->getValue($connection),
            'A refused status must roll back the other fields written by the same request.',
        );
    }

    /**
     * The order as the API serves it, with only the status replaced: what a client
     * editing an order and picking a new status sends back.
     *
     * @return array<string, mixed>
     */
    private function bodyWithStatus(string $token, int $orderId, string $statusCode): array
    {
        $response = $this->jsonRequest('GET', '/api/admin/orders/'.$orderId, [], $token);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $body['orderStatus'] = '/api/admin/order_statutes/'.$this->orderStatus($statusCode)->getId();

        return $body;
    }

    private function sentOrderWithOneLine(): Order
    {
        $order = $this->createFixtureFactory()->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $orderProduct = (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('ref')
            ->setProductSaleElementsRef('pse-ref')
            ->setTitle('Product')
            ->setQuantity(1.0)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0);
        $orderProduct->save();
        (new OrderProductTax())
            ->setOrderProductId($orderProduct->getId())
            ->setTitle('VAT')
            ->setAmount('2.000000')
            ->setPromoAmount('2.000000')
            ->save();

        return $order;
    }

    /**
     * @param list<string> $toCodes
     */
    private function allowOnly(string $fromCode, array $toCodes): void
    {
        $this->getService(OrderStatusTransitionWriter::class)->replaceTargets(
            $this->orderStatus($fromCode)->getId(),
            array_map(fn (string $code): int => $this->orderStatus($code)->getId(), $toCodes),
        );
    }

    private function orderStatus(string $code): OrderStatus
    {
        $status = OrderStatusQuery::create()->findOneByCode($code);
        self::assertNotNull($status, "Seeded order status '$code' is missing.");

        return $status;
    }
}
