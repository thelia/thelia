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

namespace Thelia\Tests\Integration\Action;

use Propel\Runtime\Propel;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\Export;
use Thelia\Model\ExportCategory;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Import;
use Thelia\Model\ImportCategory;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ExportImportActionTest extends ActionIntegrationTestCase
{
    private function createExportCategory(): ExportCategory
    {
        $con = Propel::getConnection('TheliaMain');
        $cat = new ExportCategory();
        $cat->setRef('test-export-cat-'.uniqid());
        $cat->setPosition(1);
        $cat->setLocale('en_US');
        $cat->setTitle('Test Export Category');
        $cat->save($con);

        return $cat;
    }

    private function createExport(ExportCategory $category, int $position = 1): Export
    {
        $con = Propel::getConnection('TheliaMain');
        $export = new Export();
        $export->setRef('test-export-'.uniqid());
        $export->setPosition($position);
        $export->setExportCategoryId($category->getId());
        $export->setHandleClass('Thelia\\ImportExport\\Export\\ProductExport');
        $export->setLocale('en_US');
        $export->setTitle('Test Export');
        $export->save($con);

        return $export;
    }

    private function createImportCategory(): ImportCategory
    {
        $con = Propel::getConnection('TheliaMain');
        $cat = new ImportCategory();
        $cat->setRef('test-import-cat-'.uniqid());
        $cat->setPosition(1);
        $cat->setLocale('en_US');
        $cat->setTitle('Test Import Category');
        $cat->save($con);

        return $cat;
    }

    private function createImport(ImportCategory $category, int $position = 1): Import
    {
        $con = Propel::getConnection('TheliaMain');
        $import = new Import();
        $import->setRef('test-import-'.uniqid());
        $import->setPosition($position);
        $import->setImportCategoryId($category->getId());
        $import->setHandleClass('Thelia\\ImportExport\\Import\\ProductImport');
        $import->setLocale('en_US');
        $import->setTitle('Test Import');
        $import->save($con);

        return $import;
    }

    public function testExportChangePosition(): void
    {
        $category = $this->createExportCategory();
        $export1 = $this->createExport($category, 1);
        $this->createExport($category, 2);

        $event = new UpdatePositionEvent(
            $export1->getId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            2,
        );

        $this->dispatch($event, TheliaEvents::EXPORT_CHANGE_POSITION);

        self::assertSame(2, ExportQuery::create()->findPk($export1->getId())->getPosition());
    }

    public function testExportCategoryChangePosition(): void
    {
        $cat1 = $this->createExportCategory();
        $cat2 = $this->createExportCategory();

        $event = new UpdatePositionEvent(
            $cat1->getId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            $cat2->getPosition(),
        );

        $this->dispatch($event, TheliaEvents::EXPORT_CATEGORY_CHANGE_POSITION);

        self::assertSame(
            $cat2->getPosition(),
            ExportCategoryQuery::create()->findPk($cat1->getId())->getPosition(),
        );
    }

    public function testImportChangePosition(): void
    {
        $category = $this->createImportCategory();
        $import1 = $this->createImport($category, 1);
        $this->createImport($category, 2);

        $event = new UpdatePositionEvent(
            $import1->getId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            2,
        );

        $this->dispatch($event, TheliaEvents::IMPORT_CHANGE_POSITION);

        self::assertSame(2, ImportQuery::create()->findPk($import1->getId())->getPosition());
    }

    public function testImportCategoryChangePosition(): void
    {
        $cat1 = $this->createImportCategory();
        $cat2 = $this->createImportCategory();

        $event = new UpdatePositionEvent(
            $cat1->getId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            $cat2->getPosition(),
        );

        $this->dispatch($event, TheliaEvents::IMPORT_CATEGORY_CHANGE_POSITION);

        self::assertSame(
            $cat2->getPosition(),
            ImportCategoryQuery::create()->findPk($cat1->getId())->getPosition(),
        );
    }
}
