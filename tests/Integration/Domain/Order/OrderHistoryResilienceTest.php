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

use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
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

    public function testAHistoryWriteThatFailsDoesNotBreakTheStatusChange(): void
    {
        $order = $this->factory->order();

        // The author of the line is the module that dispatched the change, and the
        // column that holds its code is 255 characters wide: the insert is refused.
        $this->changeStatus($order, OrderStatus::CODE_PAID, str_repeat('x', 300));

        OrderTableMap::clearInstancePool();
        $reloaded = OrderQuery::create()->findPk($order->getId());

        self::assertSame(OrderStatus::CODE_PAID, $reloaded?->getOrderStatus()->getCode(), 'The status change went through.');
        self::assertSame(
            0,
            OrderHistoryQuery::create()->filterByOrderId($order->getId())->count(),
            'Precondition: the history write did fail.',
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
