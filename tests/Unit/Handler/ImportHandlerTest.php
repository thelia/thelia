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

namespace Thelia\Tests\Unit\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Archiver\Archiver\TarArchiver;
use Thelia\Core\Archiver\Archiver\TarBz2Archiver;
use Thelia\Core\Archiver\Archiver\TarGzArchiver;
use Thelia\Core\Archiver\Archiver\ZipArchiver;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Serializer\Serializer\CSVSerializer;
use Thelia\Core\Serializer\Serializer\JSONSerializer;
use Thelia\Core\Serializer\Serializer\XMLSerializer;
use Thelia\Core\Serializer\Serializer\YAMLSerializer;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Handler\ImportHandler;

/**
 * The import page advertises the formats the serializers and archivers can read.
 * Nothing used to enforce that promise: the file was moved to the import cache
 * directory and handed to a serializer matched on a substring of its name, so
 * "products.csv.php" was read as a CSV file.
 *
 * The archivers are subclassed here to pin their availability, which depends on
 * the php extensions of the host and is not what these tests are about.
 */
final class ImportHandlerTest extends TestCase
{
    private ?Translator $previousTranslator = null;

    protected function setUp(): void
    {
        $this->previousTranslator = $this->translatorInstance()->getValue();

        // validateUpload() builds its message through the singleton.
        new Translator(new RequestStack());
    }

    protected function tearDown(): void
    {
        $this->translatorInstance()->setValue(null, $this->previousTranslator);
    }

    public function testAcceptedExtensionsAreDerivedFromTheRegisteredHandlers(): void
    {
        $extensions = $this->createImportHandler()->getAcceptedExtensions();

        self::assertContains('csv', $extensions);
        self::assertContains('json', $extensions);
        self::assertContains('xml', $extensions);
        self::assertContains('yaml', $extensions);
        self::assertContains('tar', $extensions);
        self::assertContains('tgz', $extensions);
        self::assertContains('bz2', $extensions);
        self::assertContains('zip', $extensions);
        self::assertNotContains('exe', $extensions);
        self::assertNotContains('php', $extensions);
    }

    public function testAcceptedMimeTypesAreDerivedFromTheRegisteredHandlers(): void
    {
        $mimeTypes = $this->createImportHandler()->getAcceptedMimeTypes();

        self::assertContains('text/csv', $mimeTypes);
        self::assertContains('application/zip', $mimeTypes);
    }

    public function testAFileFormatNoHandlerCanReadIsRefused(): void
    {
        $this->expectException(FormValidationException::class);

        $this->createImportHandler()->validateUpload('payload.exe');
    }

    public function testAFileWithoutExtensionIsRefused(): void
    {
        $this->expectException(FormValidationException::class);

        $this->createImportHandler()->validateUpload('payload');
    }

    public function testAnAcceptedExtensionDoesNotWhitewashATrailingExecutableOne(): void
    {
        $this->expectException(FormValidationException::class);

        $this->createImportHandler()->validateUpload('products.csv.php');
    }

    public function testAnExecutableExtensionIsRefusedEvenBehindAnAcceptedOne(): void
    {
        $this->expectException(FormValidationException::class);

        $this->createImportHandler()->validateUpload('products.php.csv');
    }

    public function testTheRefusalMessageListsTheAcceptedFormats(): void
    {
        try {
            $this->createImportHandler()->validateUpload('payload.exe');
        } catch (FormValidationException $exception) {
            self::assertStringContainsString('exe', $exception->getMessage());
            self::assertStringContainsString('csv', $exception->getMessage());

            return;
        }

        self::fail('"payload.exe" should not be an acceptable import file');
    }

    /**
     * @dataProvider acceptedFileNameProvider
     */
    public function testALegitimateImportFileIsAccepted(string $fileName): void
    {
        $handler = $this->createImportHandler();

        $handler->validateUpload($fileName);

        // validateUpload() throws when the file is refused, so the file name having
        // survived the call is the assertion.
        self::assertNotNull($handler->matchSerializerByExtension($fileName) ?? $handler->matchArchiverByExtension($fileName));
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function acceptedFileNameProvider(): array
    {
        return [
            ['products.csv'],
            // Every export file carries the date and a uniqid before its name.
            ['20260812-68ab1c2d-products.csv'],
            ['products.CSV'],
            ['catalogue.zip'],
            // What an archived export of the back office is downloaded as.
            ['catalogue.tgz'],
            ['catalogue.tar'],
            ['catalogue.bz2'],
            // What "tar czf" produces, and what the substring lookup used to accept.
            ['catalogue.tar.gz'],
            ['catalogue.tar.bz2'],
            // What an export archive is actually named on disk.
            ['20260812-68ab1c2d-products.csv.tgz'],
        ];
    }

    public function testASerializerIsOnlyMatchedOnTheActualExtension(): void
    {
        $handler = $this->createImportHandler();

        self::assertInstanceOf(CSVSerializer::class, $handler->matchSerializerByExtension('products.csv'));
        // "products.csv.php" used to resolve to the CSV serializer, because the lookup
        // searched ".csv" anywhere in the name.
        self::assertNull($handler->matchSerializerByExtension('products.csv.php'));
        self::assertNull($handler->matchSerializerByExtension('csv.php'));
        self::assertNull($handler->matchSerializerByExtension('products.php'));
    }

    public function testAnArchiverIsOnlyMatchedOnTheActualExtension(): void
    {
        $handler = $this->createImportHandler();

        self::assertInstanceOf(ZipArchiver::class, $handler->matchArchiverByExtension('catalogue.zip'));
        self::assertNull($handler->matchArchiverByExtension('catalogue.zip.php'));
        self::assertNull($handler->matchArchiverByExtension('products.csv'));
    }

    public function testACompoundArchiveExtensionResolvesToItsCompressedArchiver(): void
    {
        $handler = $this->createImportHandler();

        // "catalogue.tar.gz" used to be swallowed by the plain tar archiver, which
        // happened to work because PharData reads a compressed tar anyway. Those files
        // stay importable, on the archiver that owns the compression.
        self::assertInstanceOf(TarGzArchiver::class, $handler->matchArchiverByExtension('catalogue.tar.gz'));
        self::assertInstanceOf(TarBz2Archiver::class, $handler->matchArchiverByExtension('catalogue.tar.bz2'));
        self::assertInstanceOf(TarGzArchiver::class, $handler->matchArchiverByExtension('catalogue.tgz'));
        self::assertInstanceOf(TarArchiver::class, $handler->matchArchiverByExtension('catalogue.tar'));
    }

    public function testAnUnavailableArchiverIsNeitherAdvertisedNorMatched(): void
    {
        $handler = $this->createImportHandler(
            (new ArchiverManager())->setArchivers([$this->zipArchiver(), $this->unavailableTarGzArchiver()])
        );

        $extensions = $handler->getAcceptedExtensions();

        self::assertContains('zip', $extensions);
        self::assertNotContains('tgz', $extensions);
        self::assertNotContains('tar.gz', $extensions);
        self::assertNull($handler->matchArchiverByExtension('catalogue.tar.gz'));
    }

    private function createImportHandler(ArchiverManager $archiverManager = null): ImportHandler
    {
        $serializerManager = (new SerializerManager())->setSerializers([
            new CSVSerializer(),
            new JSONSerializer(),
            new XMLSerializer(),
            new YAMLSerializer(),
        ]);

        $archiverManager ??= (new ArchiverManager())->setArchivers([
            $this->tarArchiver(),
            $this->tarBz2Archiver(),
            $this->tarGzArchiver(),
            $this->zipArchiver(),
        ]);

        return new ImportHandler(new EventDispatcher(), $serializerManager, $archiverManager);
    }

    private function tarArchiver(): TarArchiver
    {
        return new class() extends TarArchiver {
            public function isAvailable()
            {
                return true;
            }
        };
    }

    private function tarGzArchiver(): TarGzArchiver
    {
        return new class() extends TarGzArchiver {
            public function isAvailable()
            {
                return true;
            }
        };
    }

    private function unavailableTarGzArchiver(): TarGzArchiver
    {
        return new class() extends TarGzArchiver {
            public function isAvailable()
            {
                return false;
            }
        };
    }

    private function tarBz2Archiver(): TarBz2Archiver
    {
        return new class() extends TarBz2Archiver {
            public function isAvailable()
            {
                return true;
            }
        };
    }

    private function zipArchiver(): ZipArchiver
    {
        return new class() extends ZipArchiver {
            public function isAvailable()
            {
                return true;
            }
        };
    }

    private function translatorInstance(): \ReflectionProperty
    {
        $instance = new \ReflectionProperty(Translator::class, 'instance');
        $instance->setAccessible(true);

        return $instance;
    }
}
