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

use Thelia\Core\Event\OrderStatus\OrderStatusCreateEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusDeleteEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class OrderStatusActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsOrderStatusWithUniqueCode(): void
    {
        $event = new OrderStatusCreateEvent();
        $event
            ->setCode('shipped_test')
            ->setColor('#00ff00')
            ->setLocale('en_US')
            ->setTitle('Shipped test')
            ->setDescription(null);

        $this->dispatch($event, TheliaEvents::ORDER_STATUS_CREATE);

        $status = $event->getOrderStatus();
        self::assertNotNull($status);
        self::assertSame('shipped_test', $status->getCode());
        self::assertSame('Shipped test', $status->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesTitleAndColor(): void
    {
        $status = $this->factory->orderStatus(['code' => 'tmp_custom', 'title' => 'Old']);

        $event = new OrderStatusUpdateEvent($status->getId());
        $event
            ->setCode('tmp_custom')
            ->setColor('#ff0000')
            ->setLocale('en_US')
            ->setTitle('Updated')
            ->setDescription(null);

        $this->dispatch($event, TheliaEvents::ORDER_STATUS_UPDATE);

        $reloaded = OrderStatusQuery::create()->findPk($status->getId());
        self::assertSame('#ff0000', $reloaded->getColor());
        self::assertSame('Updated', $reloaded->setLocale('en_US')->getTitle());
    }

    public function testUpdatePersistsEquivalentCode(): void
    {
        $status = $this->factory->orderStatus(['code' => 'paid_on_delivery', 'title' => 'Paid on delivery']);

        $event = new OrderStatusUpdateEvent($status->getId());
        $event
            ->setCode('paid_on_delivery')
            ->setEquivalentCode(OrderStatus::CODE_PAID)
            ->setColor('#00ff00')
            ->setLocale('en_US')
            ->setTitle('Paid on delivery');

        $this->dispatch($event, TheliaEvents::ORDER_STATUS_UPDATE);

        $reloaded = OrderStatusQuery::create()->findPk($status->getId());
        self::assertSame(OrderStatus::CODE_PAID, $reloaded->getEquivalentCode());
        self::assertTrue($reloaded->isPaid());
    }

    public function testUpdateRejectsNonCanonicalEquivalentCode(): void
    {
        $status = $this->factory->orderStatus(['code' => 'bad_equivalence']);

        $event = new OrderStatusUpdateEvent($status->getId());
        $event
            ->setCode('bad_equivalence')
            ->setEquivalentCode('not_a_canonical_code')
            ->setColor('#00ff00')
            ->setLocale('en_US')
            ->setTitle('Bad equivalence');

        $this->expectException(\InvalidArgumentException::class);

        $this->dispatch($event, TheliaEvents::ORDER_STATUS_UPDATE);
    }

    public function testDeleteRemovesNonProtectedOrderStatus(): void
    {
        $status = $this->factory->orderStatus(['code' => 'test_to_delete']);
        $statusId = $status->getId();

        $this->dispatch(new OrderStatusDeleteEvent($statusId), TheliaEvents::ORDER_STATUS_DELETE);

        self::assertNull(OrderStatusQuery::create()->findPk($statusId));
    }
}
