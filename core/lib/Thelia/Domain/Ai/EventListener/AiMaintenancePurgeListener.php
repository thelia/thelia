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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Ai\Log\AiLogPurger;
use Thelia\Domain\Ai\Service\AiSettings;

/**
 * Empties what the AI layer must not keep: the log older than the retention of the shop (`ai_log_retention_days`),
 * expired prompts and abandoned budget reservations, and the expired sessions of the MCP server (the pool is out of
 * reach of `cache:clear`, and a client that never closes its session leaves a file behind).
 *
 * @internal
 */
final readonly class AiMaintenancePurgeListener
{
    public function __construct(
        private AiLogPurger $purger,
        private AiSettings $settings,
        #[Autowire(service: 'thelia.cache.mcp_session')]
        private CacheItemPoolInterface $mcpSessions,
    ) {
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

        $event->addResult(\sprintf('<comment>AI MCP sessions (expired):</comment> <info>%s</info>', $this->pruneMcpSessions($dryRun)));
    }

    private function pruneMcpSessions(bool $dryRun): string
    {
        if (!$this->mcpSessions instanceof PruneableInterface) {
            return 'nothing to prune';
        }

        if ($dryRun) {
            return 'to prune';
        }

        return $this->mcpSessions->prune() ? 'pruned' : 'could not be pruned';
    }
}
