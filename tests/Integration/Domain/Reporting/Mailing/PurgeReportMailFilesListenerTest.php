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

namespace Thelia\Tests\Integration\Domain\Reporting\Mailing;

use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\File\FolderFile;
use Thelia\Domain\Reporting\Mailing\EventListener\PurgeReportMailFilesListener;
use Thelia\Domain\Reporting\Mailing\ReportMailingSettings;
use Thelia\Domain\Reporting\Mailing\ReportMailStorage;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Filesystem\TestVarCleaner;

/**
 * The file of a report outlives its link by nothing: the maintenance purge removes what is
 * older than the lifetime of the links, and reports without removing on a dry run.
 */
final class PurgeReportMailFilesListenerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FolderFile::ensureFolder(ReportMailStorage::directory());
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        // Never clean a path the code under test renders: a mutation of it would point at the project root.
        self::assertSame(THELIA_ROOT.'var/report-mail', ReportMailStorage::directory());
        TestVarCleaner::remove(THELIA_ROOT.'var/report-mail');

        parent::tearDown();
    }

    public function testFilesOlderThanTheLinkLifetimeAreRemoved(): void
    {
        $expired = $this->file('-4 days');
        $valid = $this->file('-2 days');

        $event = new MaintenancePurgeEvent();
        (new PurgeReportMailFilesListener(new ReportMailingSettings()))->onMaintenancePurge($event);

        self::assertFileDoesNotExist($expired);
        self::assertFileExists($valid, 'its link still works');
        self::assertStringContainsString('1 deleted', $event->getResults()[0]);
    }

    public function testTheDryRunRemovesNothing(): void
    {
        $expired = $this->file('-4 days');

        $event = new MaintenancePurgeEvent(true);
        (new PurgeReportMailFilesListener(new ReportMailingSettings()))->onMaintenancePurge($event);

        self::assertFileExists($expired);
        self::assertStringContainsString('1 to delete', $event->getResults()[0]);
    }

    public function testTheLifetimeOfTheLinksIsTheOneOfTheShop(): void
    {
        ConfigQuery::write(ReportMailingSettings::LINK_LIFETIME_DAYS, '10');
        ConfigQuery::resetCache();
        $file = $this->file('-4 days');

        (new PurgeReportMailFilesListener(new ReportMailingSettings()))->onMaintenancePurge(new MaintenancePurgeEvent());

        self::assertFileExists($file);
    }

    private function file(string $age): string
    {
        $path = ReportMailStorage::directory().'/'.bin2hex(random_bytes(16)).'.csv';
        file_put_contents($path, 'x');
        touch($path, (new \DateTimeImmutable($age))->getTimestamp());

        return $path;
    }
}
