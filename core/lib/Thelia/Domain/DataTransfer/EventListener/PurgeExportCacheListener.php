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

readonly class PurgeExportCacheListener
{
    public function __construct(private ExportCachePurger $exportCachePurger)
    {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $deletedCount = $this->exportCachePurger->purgeOldExportFiles(THELIA_CACHE_DIR.'export'.DS);

        $event->addResult(\sprintf(
            '<comment>Export cache files:</comment> <info>%d deleted</info>',
            $deletedCount
        ));
    }
}
