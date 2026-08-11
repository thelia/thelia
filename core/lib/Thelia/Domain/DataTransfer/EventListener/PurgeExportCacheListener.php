<?php

declare(strict_types=1);

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
