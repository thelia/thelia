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
use Thelia\Domain\DataTransfer\Service\DataTransferJobPurger;
use Thelia\Domain\DataTransfer\Service\ExportCachePurger;

readonly class PurgeExportCacheListener
{
    public function __construct(
        private ExportCachePurger $exportCachePurger,
        private DataTransferJobPurger $jobPurger,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $dryRun = $event->isDryRun();
        $verb = $dryRun ? 'to delete' : 'deleted';

        $event->addResult(\sprintf('<comment>Export cache files:</comment> <info>%d %s</info>', $dryRun ? $this->exportCachePurger->countOldExportFiles() : $this->exportCachePurger->purgeOldExportFiles(), $verb));
        $event->addResult(\sprintf('<comment>Export jobs (>%d days):</comment> <info>%d %s</info>', DataTransferJobPurger::JOB_RETENTION_DAYS, $this->jobPurger->purgeExportJobs($dryRun), $verb));
        $event->addResult(\sprintf('<comment>Import jobs (>%d days):</comment> <info>%d %s</info>', DataTransferJobPurger::JOB_RETENTION_DAYS, $this->jobPurger->purgeImportJobs($dryRun), $verb));
        $event->addResult(\sprintf('<comment>Import files left behind:</comment> <info>%d %s</info>', $this->jobPurger->sweepImportStorage($dryRun), $verb));
    }
}
