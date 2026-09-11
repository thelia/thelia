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

namespace Thelia\Tests\Support\Order;

use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Moves an order through the status events, the way the back office and the
 * payment modules do, for a test that extends {@see \Thelia\Test\ActionIntegrationTestCase}.
 */
trait MovesOrders
{
    protected function moveOrderTo(Order $order, string $statusCode): void
    {
        $event = new OrderEvent($order);
        $event->setStatus($this->orderStatus($statusCode)->getId());

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    protected function orderStatus(string $code): OrderStatus
    {
        $status = OrderStatusQuery::create()->findOneByCode($code);
        self::assertNotNull($status, "Order status '$code' is missing.");

        return $status;
    }
}
