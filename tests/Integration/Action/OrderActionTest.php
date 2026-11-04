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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class OrderActionTest extends ActionIntegrationTestCase
{
    public function testUpdateStatusChangesStatusId(): void
    {
        $order = $this->factory->order();
        $paidStatus = OrderStatusQuery::create()->findOneByCode('paid');
        self::assertNotNull($paidStatus);

        $event = new OrderEvent($order);
        $event->setStatus($paidStatus->getId());
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame($paidStatus->getId(), OrderQuery::create()->findPk($order->getId())->getStatusId());
    }

    public function testUpdateStatusMovesTheStockOfTheLinesAsTheyAreStoredNotAsTheOrderHeldThem(): void
    {
        $order = $this->factory->order(null, ['statusCode' => 'not_paid']);
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(['isDefault' => false]), $this->factory->currency(), ['title' => 'Widget', 'baseQuantity' => 10]);
        $pse = $product->getDefaultSaleElements();
        $line = $this->factory->orderProduct($order, [
            'productRef' => $product->getRef(),
            'productSaleElementsRef' => $pse->getRef(),
            'productSaleElementsId' => $pse->getId(),
            'quantity' => 2.0,
            'price' => '10.000000',
            'promoPrice' => '10.000000',
        ]);
        self::assertCount(1, $order->getOrderProducts());

        $this->getPropelConnection()->exec('UPDATE `order_product` SET `quantity` = 5 WHERE `id` = '.(int) $line->getId());

        $stock = (float) ProductSaleElementsQuery::create()->findPk($pse->getId())->getQuantity();
        $paidStatus = OrderStatusQuery::create()->findOneByCode('paid');
        self::assertNotNull($paidStatus);

        $event = new OrderEvent($order);
        $event->setStatus($paidStatus->getId());
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame(5.0, (float) OrderProductQuery::create()->findPk($line->getId())->getQuantity());
        self::assertEqualsWithDelta($stock - 5.0, (float) ProductSaleElementsQuery::create()->findPk($pse->getId())->getQuantity(), 0.0001);
    }

    /**
     * The kernel drops ONLY_FULL_GROUP_BY from the session of a MySQL server, so a query
     * that breaks under it goes unnoticed until a connection that skipped that correction
     * (a process booted with a verdict cached for another server, a MariaDB configured
     * with the mode) runs it.
     */
    public function testUpdateStatusRunsUnderOnlyFullGroupBy(): void
    {
        $connection = $this->getPropelConnection();
        $originalMode = (string) $connection->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $order = $this->factory->order();
        $paidStatus = OrderStatusQuery::create()->findOneByCode('paid');
        self::assertNotNull($paidStatus);

        try {
            $connection->query("SET SESSION sql_mode=CONCAT(@@SESSION.sql_mode, ',ONLY_FULL_GROUP_BY')");

            $event = new OrderEvent($order);
            $event->setStatus($paidStatus->getId());
            $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
        } finally {
            $connection->query("SET SESSION sql_mode='".$originalMode."'");
        }

        self::assertSame($paidStatus->getId(), OrderQuery::create()->findPk($order->getId())->getStatusId());
    }

    public function testUpdateDeliveryRefPersistsTheGivenReference(): void
    {
        $order = $this->factory->order();

        $event = new OrderEvent($order);
        $event->setDeliveryRef('TRACK-12345');
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_DELIVERY_REF);

        self::assertSame(
            'TRACK-12345',
            OrderQuery::create()->findPk($order->getId())->getDeliveryRef(),
        );
    }

    public function testUpdateTransactionRefPersistsValue(): void
    {
        $order = $this->factory->order();

        $event = new OrderEvent($order);
        $event->setTransactionRef('TXN-ABC-123');
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_TRANSACTION_REF);

        self::assertSame(
            'TXN-ABC-123',
            OrderQuery::create()->findPk($order->getId())->getTransactionRef(),
        );
    }

    public function testUpdateAddressChangesOrderAddress(): void
    {
        $order = $this->factory->order();
        $invoiceAddressId = $order->getInvoiceOrderAddressId();
        $orderAddress = OrderAddressQuery::create()->findPk($invoiceAddressId);
        self::assertNotNull($orderAddress);

        $event = new OrderAddressEvent(
            $orderAddress->getCustomerTitleId(),
            $orderAddress->getFirstname(),
            'NewLastname',
            $orderAddress->getAddress1(),
            $orderAddress->getAddress2(),
            $orderAddress->getAddress3(),
            $orderAddress->getZipcode(),
            $orderAddress->getCity(),
            $orderAddress->getCountryId(),
            $orderAddress->getPhone(),
            $orderAddress->getCellphone(),
            $orderAddress->getCompany(),
            $orderAddress->getStateId(),
        );
        $event->setOrderAddress($orderAddress);

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_ADDRESS);

        $reloaded = OrderAddressQuery::create()->findPk($invoiceAddressId);
        self::assertSame('NewLastname', $reloaded->getLastname());
    }

    public function testFactoryAssignsAutoGeneratedRef(): void
    {
        $order = $this->factory->order();

        self::assertMatchesRegularExpression('/^ORD/', $order->getRef());
    }
}
