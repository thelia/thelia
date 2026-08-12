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

use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\Export\Type\MailingExport;
use Thelia\Domain\DataTransfer\Export\Type\OrderExport;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Domain\DataTransfer\Service\HandlerCleaner;
use Thelia\Model\Export;
use Thelia\Model\ExportCategory;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Import;
use Thelia\Model\ImportCategory;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Exports and imports are declared by modules, but their tables hold no module id,
 * so removing a module used to leave entries behind pointing at a class that no
 * longer exists. Ownership is read back from the handler class namespace.
 */
final class HandlerCleanerTest extends IntegrationTestCase
{
    private const MISSING_EXPORT_CLASS = 'AcmeGone\Export\GoneExport';
    private const MISSING_IMPORT_CLASS = 'AcmeGone\Import\GoneImport';

    public function testEntriesDeclaredByAModuleAreRemovedWithTheirEmptyCategories(): void
    {
        $exportCategory = $this->createExportCategory();
        $export = $this->createExport($exportCategory, self::MISSING_EXPORT_CLASS);
        $importCategory = $this->createImportCategory();
        $import = $this->createImport($importCategory, self::MISSING_IMPORT_CLASS);

        $removed = $this->cleaner()->removeHandlersProvidedBy('AcmeGone\AcmeGone');

        self::assertSame(2, $removed);
        self::assertNull(ExportQuery::create()->findPk($export->getId()));
        self::assertNull(ImportQuery::create()->findPk($import->getId()));
        self::assertNull(ExportCategoryQuery::create()->findPk($exportCategory->getId()));
        self::assertNull(ImportCategoryQuery::create()->findPk($importCategory->getId()));
    }

    public function testEntriesDeclaredByAnotherModuleAreLeftAlone(): void
    {
        $export = $this->createExport($this->createExportCategory(), self::MISSING_EXPORT_CLASS);

        self::assertSame(0, $this->cleaner()->removeHandlersProvidedBy('OtherModule\OtherModule'));
        self::assertNotNull(ExportQuery::create()->findPk($export->getId()));
    }

    public function testEntriesWhoseHandlerIsStillInstalledSurviveTheOrphanSweep(): void
    {
        $category = $this->createExportCategory();
        $orphan = $this->createExport($category, self::MISSING_EXPORT_CLASS);
        $installed = $this->createExport($category, MailingExport::class);

        $this->cleaner()->removeUnavailableHandlers();

        self::assertNull(ExportQuery::create()->findPk($orphan->getId()));
        self::assertNotNull(ExportQuery::create()->findPk($installed->getId()));
        // The category still holds an export, so it is kept.
        self::assertNotNull(ExportCategoryQuery::create()->findPk($category->getId()));
    }

    public function testAMissingHandlerIsReportedWithoutTouchingTheDatabase(): void
    {
        $export = $this->createExport($this->createExportCategory(), self::MISSING_EXPORT_CLASS);

        self::assertFalse($export->isHandlerAvailable());
        self::assertFalse($export->hasImages());
        self::assertFalse($export->hasDocuments());
        self::assertFalse($export->useRangeDate());

        // Reading the flags of a broken export must not delete it: only an explicit
        // module removal or `import-export:clean` may.
        self::assertNotNull(ExportQuery::create()->findPk($export->getId()));
    }

    public function testAnExportFlagIsNotBorrowedFromAPreviouslyReadExport(): void
    {
        $category = $this->createExportCategory();
        $ranged = $this->createExport($category, OrderExport::class);
        $notRanged = $this->createExport($category, MailingExport::class);
        $orphan = $this->createExport($category, self::MISSING_EXPORT_CLASS);

        // The handler used to be cached statically, so every export read afterwards
        // answered with the flags of the first one.
        self::assertTrue($ranged->useRangeDate());
        self::assertFalse($notRanged->useRangeDate());
        self::assertFalse($orphan->useRangeDate());
    }

    public function testRunningAnExportWithAMissingHandlerReportsWhyRatherThanFataling(): void
    {
        $export = $this->createExport($this->createExportCategory(), self::MISSING_EXPORT_CLASS);

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessageMatches('/AcmeGone\\\\Export\\\\GoneExport/');

        $this->getService(ExportHandler::class)->export(
            $export,
            $this->getService(SerializerManager::class)->get('thelia.csv'),
        );
    }

    public function testRunningAnImportWithAMissingHandlerReportsWhyRatherThanFataling(): void
    {
        $import = $this->createImport($this->createImportCategory(), self::MISSING_IMPORT_CLASS);

        $file = new \Symfony\Component\HttpFoundation\File\File(
            $this->writeTemporaryCsv(),
        );

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessageMatches('/AcmeGone\\\\Import\\\\GoneImport/');

        $this->getService(ImportHandler::class)->import($import, $file);
    }

    private function cleaner(): HandlerCleaner
    {
        return new HandlerCleaner();
    }

    private function writeTemporaryCsv(): string
    {
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('handler-cleaner-', true).'.csv';
        file_put_contents($path, "ref;stock\n");

        return $path;
    }

    private function createExportCategory(): ExportCategory
    {
        $category = new ExportCategory();
        $category->setRef('acme-gone-export-'.uniqid());
        $category->setPosition(1);
        $category->setLocale('en_US');
        $category->setTitle('Acme gone');
        $category->save($this->getPropelConnection());

        return $category;
    }

    private function createExport(ExportCategory $category, string $handleClass): Export
    {
        $export = new Export();
        $export->setRef('acme-gone-export-'.uniqid());
        $export->setExportCategoryId($category->getId());
        $export->setHandleClass($handleClass);
        $export->setLocale('en_US');
        $export->setTitle('Acme gone export');
        $export->save($this->getPropelConnection());

        return $export;
    }

    private function createImportCategory(): ImportCategory
    {
        $category = new ImportCategory();
        $category->setRef('acme-gone-import-'.uniqid());
        $category->setPosition(1);
        $category->setLocale('en_US');
        $category->setTitle('Acme gone');
        $category->save($this->getPropelConnection());

        return $category;
    }

    private function createImport(ImportCategory $category, string $handleClass): Import
    {
        $import = new Import();
        $import->setRef('acme-gone-import-'.uniqid());
        $import->setImportCategoryId($category->getId());
        $import->setHandleClass($handleClass);
        $import->setLocale('en_US');
        $import->setTitle('Acme gone import');
        $import->save($this->getPropelConnection());

        return $import;
    }
}
