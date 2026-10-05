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

namespace Thelia\Domain\DataTransfer\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\DataTransfer\Service\ExportCachePurger;
use Thelia\Model\ExportJobQuery;

readonly class PurgeExportCacheListener
{
    public const EXPORT_JOB_RETENTION_DAYS = 7;

    public function __construct(private ExportCachePurger $exportCachePurger)
    {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $deletedCount = $this->exportCachePurger->purgeOldExportFiles(THELIA_CACHE_DIR.'export'.DS, $event->isDryRun());

        $event->addResult(\sprintf(
            '<comment>Export cache files:</comment> <info>%d %s</info>',
            $deletedCount,
            $event->isDryRun() ? 'to delete' : 'deleted',
        ));

        // A job outlives its file by a few days, so the back office can still say
        // what was exported and why an export failed.
        $deletedJobs = ExportJobQuery::purgeCreatedBefore(self::EXPORT_JOB_RETENTION_DAYS, $event->isDryRun());

        $event->addResult(\sprintf(
            '<comment>Export jobs (>%d days):</comment> <info>%d %s</info>',
            self::EXPORT_JOB_RETENTION_DAYS,
            $deletedJobs,
            $event->isDryRun() ? 'to delete' : 'deleted',
        ));
    }
}
