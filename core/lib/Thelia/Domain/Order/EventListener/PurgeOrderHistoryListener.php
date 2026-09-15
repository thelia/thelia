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

namespace Thelia\Domain\Order\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Service\OrderHistoryPurger;
use Thelia\Model\ConfigQuery;

/**
 * Hooks the order history onto `maintenance:purge`, the job a shop already runs
 * to apply its retention periods, rather than onto a command of its own.
 *
 * The period is read the same way every other section of that job reads its own:
 * from a `purification_*` configuration entry. It is off until the shop sets one,
 * for the reason customer retention is off until then — the history says who did
 * what to an order and when, a dispute is argued with it, and nobody but the shop
 * knows how long they are required to be able to answer. A period of 0 keeps it off.
 */
readonly class PurgeOrderHistoryListener
{
    public const RETENTION_DAYS_CONFIG_KEY = 'purification_order_history_days';

    public function __construct(
        private OrderHistoryPurger $orderHistoryPurger,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $retentionDays = (int) ConfigQuery::read(self::RETENTION_DAYS_CONFIG_KEY, 0);

        if ($retentionDays <= 0) {
            $event->addResult(\sprintf(
                '<comment>Order history:</comment> <info>retention disabled (set %s to enable it)</info>',
                self::RETENTION_DAYS_CONFIG_KEY,
            ));

            return;
        }

        $dryRun = $event->isDryRun();

        $entries = $dryRun
            ? $this->orderHistoryPurger->countOrderHistory($retentionDays)
            : $this->orderHistoryPurger->purgeOrderHistory($retentionDays);

        $event->addResult(\sprintf(
            '<comment>Order history entries (>%d days):</comment> <info>%d %s</info>',
            $retentionDays,
            $entries,
            $dryRun ? 'to delete' : 'deleted',
        ));
    }
}
