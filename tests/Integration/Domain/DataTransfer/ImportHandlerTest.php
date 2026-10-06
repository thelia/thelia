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

use Symfony\Component\HttpFoundation\File\File;
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
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), array_filter($this->temporaryFiles, is_file(...)));

        parent::tearDown();
    }

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

        // Each call throws when the file is refused.
        $handler->validateUpload('products.csv');
        $handler->validateUpload('68ab1c2d.12345678-products.CSV');
        $handler->validateUpload('catalogue.zip');
    }

    /**
     * The name is chosen by whoever uploads the file: a program renamed products.csv
     * is refused for what it holds.
     */
    public function testAFileWhoseContentIsNotItsFormatIsRefused(): void
    {
        $path = $this->temporaryFile("\x7FELF\x02\x01\x01\x00".str_repeat("\x00", 64));

        $this->expectException(FormValidationException::class);

        $this->importHandler()->validateUpload('products.csv', new File($path));
    }

    public function testAnArchiveThatIsNotOneIsRefused(): void
    {
        $path = $this->temporaryFile("id,stock\n1,2\n");

        $this->expectException(FormValidationException::class);

        $this->importHandler()->validateUpload('catalogue.zip', new File($path));
    }

    public function testAFileWhoseContentIsItsFormatIsAccepted(): void
    {
        $handler = $this->importHandler();

        $handler->validateUpload('products.csv', new File($this->temporaryFile("id,stock\n1,2\n")));
        $handler->validateUpload('products.json', new File($this->temporaryFile('[{"id":1,"stock":2}]')));

        $zip = $this->temporaryFile('');
        $archive = new \ZipArchive();
        $archive->open($zip, \ZipArchive::OVERWRITE);
        $archive->addFromString('products.csv', "id,stock\n1,2\n");
        $archive->close();
        $handler->validateUpload('catalogue.zip', new File($zip));

        $this->addToAssertionCount(1);
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

    private function temporaryFile(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'import-content');
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
