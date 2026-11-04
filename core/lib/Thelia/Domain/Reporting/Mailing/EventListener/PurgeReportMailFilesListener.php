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

namespace Thelia\Domain\Reporting\Mailing\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FolderFile;
use Thelia\Domain\Reporting\Mailing\ReportMailingSettings;
use Thelia\Domain\Reporting\Mailing\ReportMailStorage;

/**
 * Removes the files of the reports sent as a link once their link has expired: the file
 * outlives its link by nothing, as it holds the data of the shop.
 */
readonly class PurgeReportMailFilesListener
{
    public function __construct(private ReportMailingSettings $settings)
    {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $expired = ReportMailStorage::expiredFiles($this->settings->linkLifetimeDays());
        $dryRun = $event->isDryRun();
        $removed = 0;

        if (!$dryRun) {
            foreach ($expired as $path) {
                if (FolderFile::remove(ReportMailStorage::directory(), $path)) {
                    ++$removed;
                }
            }
        }

        $event->addResult(\sprintf('<comment>Report files:</comment> <info>%d %s</info>', $dryRun ? \count($expired) : $removed, $dryRun ? 'to delete' : 'deleted'));
    }
}
