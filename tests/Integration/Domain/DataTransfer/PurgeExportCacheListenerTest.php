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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Domain\DataTransfer\EventListener\PurgeExportCacheListener;
use Thelia\Domain\DataTransfer\Service\ExportCachePurger;
use Thelia\Test\IntegrationTestCase;

/**
 * maintenance:purge --dry-run promises to write nothing: the export cache files are
 * counted, never deleted.
 */
final class PurgeExportCacheListenerTest extends IntegrationTestCase
{
    public function testADryRunCountsAndNeverDeletes(): void
    {
        $purger = $this->createMock(ExportCachePurger::class);
        $purger->expects(self::never())->method('purgeOldExportFiles');
        $purger->expects(self::once())->method('countOldExportFiles')->willReturn(4);
        $event = new MaintenancePurgeEvent(true);

        (new PurgeExportCacheListener($purger))->onMaintenancePurge($event);

        self::assertStringContainsString('4 to delete', implode("\n", $event->getResults()));
    }

    public function testARealPurgeDeletes(): void
    {
        $purger = $this->createMock(ExportCachePurger::class);
        $purger->expects(self::never())->method('countOldExportFiles');
        $purger->expects(self::once())->method('purgeOldExportFiles')->willReturn(4);
        $event = new MaintenancePurgeEvent();

        (new PurgeExportCacheListener($purger))->onMaintenancePurge($event);

        self::assertStringContainsString('4 deleted', implode("\n", $event->getResults()));
    }
}
