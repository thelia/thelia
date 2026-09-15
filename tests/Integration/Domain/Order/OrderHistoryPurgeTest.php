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

use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\EventListener\PurgeOrderHistoryListener;
use Thelia\Domain\Order\Service\OrderHistoryPurger;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The retention period a shop applies to the history of its orders, and the
 * maintenance job that applies it.
 */
final class OrderHistoryPurgeTest extends ActionIntegrationTestCase
{
    protected function tearDown(): void
    {
        // The row is rolled back with the wrapper transaction, the static cache is not.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testOnlyTheEntriesPastTheRetentionPeriodAreCounted(): void
    {
        $order = $this->factory->order();

        $this->entryAgedDays($order, 400);
        $this->entryAgedDays($order, 200);
        $this->entryAgedDays($order, 10);

        $purger = $this->getService(OrderHistoryPurger::class);

        self::assertSame(2, $purger->countOrderHistory(180));
    }

    public function testTheEntriesPastTheRetentionPeriodAreDeletedAndTheOthersKept(): void
    {
        $order = $this->factory->order();

        $this->entryAgedDays($order, 400);
        $recent = $this->entryAgedDays($order, 10);

        $deleted = $this->getService(OrderHistoryPurger::class)->purgeOrderHistory(180);

        self::assertSame(1, $deleted);

        $remaining = OrderHistoryQuery::create()->filterByOrderId($order->getId())->find()->getData();
        self::assertCount(1, $remaining);
        self::assertSame($recent->getId(), $remaining[0]->getId());
    }

    /**
     * Erasing an audit trail cannot be undone and a shop is the only one that knows
     * how long it has to be able to answer for an order, so nothing is deleted until
     * a period is configured.
     */
    public function testTheMaintenanceJobDeletesNothingUntilAPeriodIsConfigured(): void
    {
        $order = $this->factory->order();
        $this->entryAgedDays($order, 4000);

        $event = $this->runMaintenancePurge();

        self::assertCount(1, OrderHistoryQuery::create()->filterByOrderId($order->getId())->find()->getData());
        self::assertStringContainsString('retention disabled', implode("\n", $event->getResults()));
    }

    public function testTheMaintenanceJobAppliesTheConfiguredPeriod(): void
    {
        ConfigQuery::write(PurgeOrderHistoryListener::RETENTION_DAYS_CONFIG_KEY, '180');

        $order = $this->factory->order();
        $this->entryAgedDays($order, 400);
        $this->entryAgedDays($order, 10);

        $event = $this->runMaintenancePurge();

        self::assertCount(1, OrderHistoryQuery::create()->filterByOrderId($order->getId())->find()->getData());
        self::assertStringContainsString('1 deleted', implode("\n", $event->getResults()));
    }

    public function testADryRunReportsWhatItWouldDeleteAndDeletesNothing(): void
    {
        ConfigQuery::write(PurgeOrderHistoryListener::RETENTION_DAYS_CONFIG_KEY, '180');

        $order = $this->factory->order();
        $this->entryAgedDays($order, 400);
        $this->entryAgedDays($order, 500);

        $event = $this->runMaintenancePurge(dryRun: true);

        self::assertCount(2, OrderHistoryQuery::create()->filterByOrderId($order->getId())->find()->getData());
        self::assertStringContainsString('2 to delete', implode("\n", $event->getResults()));
    }

    private function runMaintenancePurge(bool $dryRun = false): MaintenancePurgeEvent
    {
        $event = new MaintenancePurgeEvent($dryRun);
        $this->dispatch($event, TheliaEvents::MAINTENANCE_PURGE);

        return $event;
    }

    private function entryAgedDays(Order $order, int $days): OrderHistory
    {
        $entry = new OrderHistory();
        $entry
            ->setOrderId($order->getId())
            ->setEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->setActorType(OrderHistoryActorType::SYSTEM->value)
            ->setPayload('{"from":"not_paid","to":"paid"}')
            ->setVisibleToCustomer(0)
            ->save($this->getPropelConnection());

        // The timestampable behavior writes created_at itself on insert, and keeps what
        // it is given only when the column was set before the save that follows.
        $entry
            ->setCreatedAt(new \DateTime(\sprintf('-%d days', $days)))
            ->save($this->getPropelConnection());

        return $entry;
    }
}
