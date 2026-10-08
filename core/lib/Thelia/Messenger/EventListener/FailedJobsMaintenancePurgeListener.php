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

namespace Thelia\Messenger\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Messenger\FailedMessagePurger;
use Thelia\Messenger\JobFailureMessage;

/**
 * Purges the failed jobs with the rest of the shop.
 *
 * A failed job may hold personal data (the address and the content of a mail), and a
 * shop that purges already runs maintenance:purge, from its crontab or the schedule:
 * the failed jobs go with it, after the same retention as thelia:messenger:purge-failed.
 */
final readonly class FailedJobsMaintenancePurgeListener
{
    public function __construct(
        private FailedMessagePurger $purger,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $dryRun = $event->isDryRun();

        // A failure transport that cannot list its jobs (a queue server set by
        // MESSENGER_FAILURE_TRANSPORT_DSN) is skipped: the rest of the purge still runs,
        // and thelia:messenger:purge-failed keeps saying why.
        try {
            $purged = $this->purger->purgeSetAsideBefore(new \DateTimeImmutable(\sprintf('-%d days', FailedMessagePurger::RETENTION_DAYS)), $dryRun);
        } catch (\LogicException $cannotList) {
            Tlog::getInstance()->addWarning(\sprintf('The failed jobs were not purged: %s', JobFailureMessage::forLog($cannotList)));
            $event->addResult('<comment>Failed jobs:</comment> <info>skipped, the failure transport cannot list its jobs</info>');

            return;
        }

        $event->addResult(\sprintf('<comment>Failed jobs (>%d days):</comment> <info>%d %s</info>', FailedMessagePurger::RETENTION_DAYS, $purged, $dryRun ? 'to delete' : 'deleted'));
    }
}
