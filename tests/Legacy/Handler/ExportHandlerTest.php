<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Core\Event\ExportEvent;
use Thelia\Core\Serializer\Serializer\CSVSerializer;
use Thelia\Handler\ExportHandler;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;

/**
 * A date range is optional. `php Thelia export` never supplies one, and the
 * back-office form only does for the exports that offer the field.
 */
class ExportHandlerTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private $writtenFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->writtenFiles = [];
    }

    public function testAnExportRunsWithoutADateRange(): void
    {
        $event = $this->runExport(null);

        $this->assertFileExists($event->getFilePath());
        $this->assertNull($event->getExport()->getRangeDate());
    }

    public function testASuppliedDateRangeIsTurnedIntoBoundaryDates(): void
    {
        $event = $this->runExport([
            'start' => ['year' => '2026', 'month' => '02'],
            'end' => ['year' => '2026', 'month' => '02'],
        ]);

        $rangeDate = $event->getExport()->getRangeDate();

        $this->assertSame('2026-02-01 00:00:00', $rangeDate['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28 23:59:59', $rangeDate['end']->format('Y-m-d H:i:s'));
    }

    /**
     * @param array|null $rangeDate
     */
    private function runExport($rangeDate): ExportEvent
    {
        // Product prices: an export every install has, over rows the demo data provides.
        $export = ExportQuery::create()->findOneByRef('thelia.export.prices');

        $this->assertNotNull($export, 'The product prices export is part of every install.');

        $event = (new ExportHandler(new EventDispatcher()))->export(
            $export,
            new CSVSerializer(),
            null,
            Lang::getDefaultLanguage(),
            false,
            false,
            $rangeDate
        );

        $this->writtenFiles[] = $event->getFilePath();

        return $event;
    }
}
