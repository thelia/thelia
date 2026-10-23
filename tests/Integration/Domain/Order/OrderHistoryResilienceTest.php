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

namespace Thelia\Tests\Integration\Domain\Order;

use Propel\Runtime\Connection\ConnectionWrapper;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\AdminQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * What the journal promises about its own robustness: it outlives the people who wrote
 * in it, and it never breaks the gesture it records.
 */
final class OrderHistoryResilienceTest extends ActionIntegrationTestCase
{
    public function testDeletingAnAdministratorKeepsTheirLinesWithTheirLogin(): void
    {
        $order = $this->factory->order();
        $admin = $this->factory->admin();
        $login = $admin->getLogin();
        $this->getService(SecurityContext::class)->setAdminUser($admin);

        $this->changeStatus($order, OrderStatus::CODE_PAID);

        $admin->delete($this->getPropelConnection());
        self::assertNull(AdminQuery::create()->findOneByLogin($login), 'Precondition: the administrator is gone.');

        $lines = OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->find();

        self::assertCount(1, $lines);
        self::assertSame(OrderHistoryActorType::ADMIN->value, $lines[0]->getActorType());
        self::assertSame($login, $lines[0]->getActorLabel());
        self::assertNull($lines[0]->getAdminId());
    }

    /**
     * The author of a line is the module that dispatched the change, and the column that
     * holds its code is 255 characters wide. A refused INSERT rolls back the transaction
     * level Propel opened for it, which marks the transaction of the caller as not
     * committable: the status change, written in that transaction, would be lost with it
     * (the API order processor wraps the change in one).
     */
    public function testALongModuleCodeIsBoundedAndTheTransactionOfTheCallerStaysCommittable(): void
    {
        $order = $this->factory->order();
        $connection = $this->getPropelConnection();
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        self::assertTrue($connection->isCommitable(), 'Precondition: a transaction is open and healthy.');

        $this->changeStatus($order, OrderStatus::CODE_PAID, str_repeat('x', 300));

        self::assertTrue($connection->isCommitable(), 'The history write must not take the transaction of the caller away.');

        OrderTableMap::clearInstancePool();
        self::assertSame(OrderStatus::CODE_PAID, OrderQuery::create()->findPk($order->getId())?->getOrderStatus()->getCode());

        $lines = OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->find();
        self::assertCount(1, $lines);
        self::assertSame(str_repeat('x', 255), $lines[0]->getActorLabel());
    }

    public function testAnEventTypeTooLongIsRefusedWithoutTouchingTheTransactionOfTheCaller(): void
    {
        $order = $this->factory->order();
        $connection = $this->getPropelConnection();
        self::assertInstanceOf(ConnectionWrapper::class, $connection);

        $this->getService(OrderHistoryRecorder::class)->record($order->getId(), str_repeat('t', 51));

        self::assertTrue($connection->isCommitable());
        self::assertSame(0, OrderHistoryQuery::create()->filterByOrderId($order->getId())->count());
    }

    /**
     * A line the database refuses (here a foreign key on an order that does not exist)
     * is logged and forgotten, and the transaction around it is none the worse: the
     * gesture of the caller that follows goes through and can still be committed. Written
     * through save(), the same refusal rolled back the level Propel opened and the
     * caller's commit would have thrown.
     */
    public function testALineTheDatabaseRefusesLeavesTheTransactionOfTheCallerCommittable(): void
    {
        $order = $this->factory->order();
        $connection = $this->getPropelConnection();
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        self::assertTrue($connection->isCommitable(), 'Precondition: a transaction is open and healthy.');

        $missingOrderId = $order->getId() + 1_000_000;
        self::assertNull(OrderQuery::create()->findPk($missingOrderId), 'Precondition: the order does not exist.');

        $this->getService(OrderHistoryRecorder::class)->record($missingOrderId, OrderHistoryEventType::NOTE->value, comment: 'refused by the foreign key');

        self::assertTrue($connection->isCommitable(), 'A refused line must not take the transaction of the caller away.');
        self::assertSame(0, OrderHistoryQuery::create()->filterByOrderId($missingOrderId)->count());

        $this->changeStatus($order, OrderStatus::CODE_PAID);

        self::assertTrue($connection->isCommitable());
        OrderTableMap::clearInstancePool();
        self::assertSame(OrderStatus::CODE_PAID, OrderQuery::create()->findPk($order->getId())?->getOrderStatus()->getCode(), 'The gesture of the caller went through.');
        self::assertSame(
            1,
            OrderHistoryQuery::create()
                ->filterByOrderId($order->getId())
                ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
                ->count(),
            'The line of the gesture that follows is written.',
        );
    }

    private function changeStatus(Order $order, string $statusCode, ?string $moduleCode = null): void
    {
        $status = OrderStatusQuery::create()->findOneByCode($statusCode);
        self::assertNotNull($status);

        $event = new OrderEvent($order);
        $event->setStatus($status->getId());

        if (null !== $moduleCode) {
            $event->setSourceModuleCode($moduleCode);
        }

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }
}
