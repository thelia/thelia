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

namespace Thelia\Tests\Integration\Domain\Webhook;

use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Webhook\WebhookDeliveryPurger;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Model\WebhookDelivery;
use Thelia\Model\WebhookDeliveryQuery;
use Thelia\Model\WebhookSubscription;
use Thelia\Model\WebhookSubscriptionQuery;
use Thelia\Test\IntegrationTestCase;

final class WebhookDeliveryPurgerTest extends IntegrationTestCase
{
    private WebhookSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        WebhookSubscriptionQuery::create()->deleteAll($this->getPropelConnection());
        $this->subscription = (new WebhookSubscription())->setTitle('Purge')->setUrl('https://hooks.example.com/in')->setSecret('s')->setEvents('[]');
        $this->subscription->save($this->getPropelConnection());
    }

    public function testOnlyTheDeliveriesOlderThanTheRetentionGo(): void
    {
        $this->deliveryDaysOld(45);
        $this->deliveryDaysOld(31);
        $recent = $this->deliveryDaysOld(5);

        $purged = (new WebhookDeliveryPurger())->purge(30, false);

        self::assertSame(2, $purged);
        self::assertSame([$recent->getId()], WebhookDeliveryQuery::create()->find($this->getPropelConnection())->getPrimaryKeys(false));
    }

    public function testADryRunCountsAndDeletesNothing(): void
    {
        $this->deliveryDaysOld(45);

        self::assertSame(1, (new WebhookDeliveryPurger())->purge(30, true));
        self::assertSame(1, WebhookDeliveryQuery::create()->count($this->getPropelConnection()));
    }

    public function testTheMaintenancePurgeReportsTheDeliveries(): void
    {
        $this->deliveryDaysOld(45);
        $event = new MaintenancePurgeEvent(false);

        static::getContainer()->get('event_dispatcher')->dispatch($event, TheliaEvents::MAINTENANCE_PURGE);

        self::assertCount(1, array_filter($event->getResults(), static fn (string $line): bool => str_contains($line, 'Webhook deliveries')));
        self::assertSame(0, WebhookDeliveryQuery::create()->count($this->getPropelConnection()));
    }

    private function deliveryDaysOld(int $days): WebhookDelivery
    {
        $delivery = (new WebhookDelivery())
            ->setWebhookSubscription($this->subscription)
            ->setEvent('order.created')
            ->setDeliveryUuid(bin2hex(random_bytes(16)))
            ->setPayload('{}')
            ->setStatus(WebhookStatus::DELIVERY_SUCCEEDED);
        $delivery->save($this->getPropelConnection());
        $this->getPropelConnection()->exec(\sprintf('UPDATE webhook_delivery SET created_at = DATE_SUB(NOW(), INTERVAL %d DAY) WHERE id = %d', $days, $delivery->getId()));

        return $delivery;
    }
}
