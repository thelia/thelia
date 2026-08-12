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

use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Test\IntegrationTestCase;

/**
 * The back office advertises the formats the import handlers can read. Nothing
 * used to enforce that promise: any file was moved to the import cache directory
 * and handed over to a serializer matched on a substring of its name.
 */
final class ImportHandlerTest extends IntegrationTestCase
{
    public function testAcceptedExtensionsAreDerivedFromTheRegisteredHandlers(): void
    {
        $extensions = $this->importHandler()->getAcceptedExtensions();

        self::assertContains('csv', $extensions);
        self::assertContains('json', $extensions);
        self::assertContains('xml', $extensions);
        self::assertContains('yaml', $extensions);
        self::assertContains('zip', $extensions);
        self::assertNotContains('exe', $extensions);
    }

    public function testAcceptedMimeTypesAreDerivedFromTheRegisteredHandlers(): void
    {
        $mimeTypes = $this->importHandler()->getAcceptedMimeTypes();

        self::assertContains('text/csv', $mimeTypes);
        self::assertContains('application/zip', $mimeTypes);
    }

    public function testAFileFormatNoHandlerCanReadIsRefused(): void
    {
        $this->expectException(FormValidationException::class);

        $this->importHandler()->validateUpload('payload.exe');
    }

    public function testAFileWithoutExtensionIsRefused(): void
    {
        $this->expectException(FormValidationException::class);

        $this->importHandler()->validateUpload('payload');
    }

    public function testAnExecutableExtensionIsRefusedEvenBehindAnAcceptedOne(): void
    {
        $this->expectException(FormValidationException::class);

        $this->importHandler()->validateUpload('products.php.csv');
    }

    public function testAnAcceptedExtensionDoesNotWhitewashAnExecutableSuffix(): void
    {
        $this->expectException(FormValidationException::class);

        $this->importHandler()->validateUpload('products.csv.php');
    }

    public function testALegitimateImportFileIsAccepted(): void
    {
        $handler = $this->importHandler();

        $handler->validateUpload('products.csv');
        $handler->validateUpload('68ab1c2d.12345678-products.CSV');
        $handler->validateUpload('catalogue.zip');

        $this->expectNotToPerformAssertions();
    }

    public function testASerializerIsOnlyMatchedOnTheActualExtension(): void
    {
        $handler = $this->importHandler();

        self::assertNotNull($handler->matchSerializerByExtension('products.csv'));
        // "products.csv.php" used to be matched as a CSV file, because the lookup
        // searched ".csv" anywhere in the name.
        self::assertNull($handler->matchSerializerByExtension('products.csv.php'));
        self::assertNull($handler->matchSerializerByExtension('csv.php'));
    }

    public function testAnArchiverIsOnlyMatchedOnTheActualExtension(): void
    {
        $handler = $this->importHandler();

        self::assertNotNull($handler->matchArchiverByExtension('catalogue.zip'));
        self::assertNull($handler->matchArchiverByExtension('catalogue.zip.php'));
    }

    private function importHandler(): ImportHandler
    {
        $handler = $this->getService(ImportHandler::class);

        self::assertInstanceOf(ImportHandler::class, $handler);

        return $handler;
    }
}
