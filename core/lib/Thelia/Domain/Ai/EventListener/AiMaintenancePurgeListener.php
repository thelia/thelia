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

namespace Thelia\Domain\Ai\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Ai\Log\AiLogPurger;
use Thelia\Domain\Ai\Service\AiSettings;

/**
 * Empties what the AI layer must not keep: the log older than the retention of the shop (`ai_log_retention_days`),
 * expired prompts and abandoned budget reservations.
 *
 * @internal
 */
final readonly class AiMaintenancePurgeListener
{
    public function __construct(private AiLogPurger $purger, private AiSettings $settings)
    {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $days = $this->settings->logRetentionDays();
        $dryRun = $event->isDryRun();
        $purged = $this->purger->purge($days, $dryRun);
        $verb = $dryRun ? 'to delete' : 'deleted';

        $event->addResult(\sprintf('<comment>AI usage log (>%d days):</comment> <info>%d %s</info>', $days, $purged->logRows, $verb));
        $event->addResult(\sprintf('<comment>AI stored prompts (expired):</comment> <info>%d %s</info>', $purged->storedPrompts, $dryRun ? 'to empty' : 'emptied'));
        $event->addResult(\sprintf('<comment>AI budget reservations (expired):</comment> <info>%d %s</info>', $purged->expiredReservations, $verb));
        $event->addResult(\sprintf('<comment>AI tool call log (>%d days):</comment> <info>%d %s</info>', $days, $purged->toolLogRows, $verb));
        $event->addResult(\sprintf('<comment>AI tool call arguments (older than 30 days):</comment> <info>%d %s</info>', $purged->toolArguments, $dryRun ? 'to empty' : 'emptied'));
        $event->addResult(\sprintf('<comment>AI tool approvals (pending, expired):</comment> <info>%d %s</info>', $purged->expiredApprovals, $dryRun ? 'to mark expired' : 'marked expired'));
        $event->addResult(\sprintf('<comment>AI tool approvals (decided, >%d days):</comment> <info>%d %s</info>', $days, $purged->decidedApprovals, $verb));
        $event->addResult(\sprintf('<comment>AI tool approval arguments (decided, older than 30 days):</comment> <info>%d %s</info>', $purged->approvalArguments, $dryRun ? 'to empty' : 'emptied'));
    }
}
