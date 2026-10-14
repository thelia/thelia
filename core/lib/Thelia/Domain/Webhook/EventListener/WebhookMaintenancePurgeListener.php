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

namespace Thelia\Domain\Webhook\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Webhook\WebhookDeliveryPurger;
use Thelia\Model\ConfigQuery;

final readonly class WebhookMaintenancePurgeListener
{
    public function __construct(private WebhookDeliveryPurger $purger)
    {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $days = (int) ConfigQuery::read(WebhookDeliveryPurger::RETENTION_CONFIG_KEY, WebhookDeliveryPurger::DEFAULT_RETENTION_DAYS);
        $dryRun = $event->isDryRun();
        $purged = $this->purger->purge($days, $dryRun);

        $event->addResult(\sprintf('<comment>Webhook deliveries (>%d days):</comment> <info>%d %s</info>', max(1, $days), $purged, $dryRun ? 'to delete' : 'deleted'));
    }
}
