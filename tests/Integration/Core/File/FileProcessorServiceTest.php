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

namespace Thelia\Tests\Integration\Core\File;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\File\Exception\ProcessFileException;
use Thelia\Core\File\FileConfiguration;
use Thelia\Core\File\Service\FileProcessorService;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * A caller that passes no constraint must still get the shop upload policy:
 * that is what the Twig back office does, and forgetting it let an administrator
 * upload any file type.
 */
final class FileProcessorServiceTest extends IntegrationTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        // The database changes are rolled back, the static config cache is not.
        ConfigQuery::resetCache();
        parent::tearDown();
    }

    public function testImageUploadWithoutExplicitConstraintsRefusesANonImage(): void
    {
        $this->expectException(ProcessFileException::class);

        $this->process('payload.exe', 'not an image', 'image');
    }

    public function testDocumentUploadWithoutExplicitConstraintsRefusesABlacklistedExtension(): void
    {
        $this->expectException(ProcessFileException::class);

        $this->process('payload.exe', 'MZ', 'document');
    }

    public function testLegitimateUploadsAreStillAccepted(): void
    {
        $this->validate('kitten.png', $this->pngBytes(), 'image');
        $this->validate('invoice.pdf', '%PDF-1.4', 'document');

        $this->expectNotToPerformAssertions();
    }

    public function testAllowedImageMimeTypesCanBeNarrowedByConfiguration(): void
    {
        ConfigQuery::write(FileConfiguration::IMAGE_MIME_TYPES_VARIABLE, 'image/png');

        $this->validate('kitten.png', $this->pngBytes(), 'image');

        $this->expectException(ProcessFileException::class);

        $this->validate('kitten.gif', 'GIF89a'.str_repeat("\x00", 16), 'image');
    }

    public function testAllowedImageMimeTypesCanBeExtendedByConfiguration(): void
    {
        ConfigQuery::write(FileConfiguration::IMAGE_MIME_TYPES_VARIABLE, 'image/png, text/plain');

        $this->validate('notes.txt', 'plain text', 'image');

        $this->expectNotToPerformAssertions();
    }

    public function testConfigurationCannotReEnableAServerExecutableExtension(): void
    {
        ConfigQuery::write(FileConfiguration::DOCUMENT_EXTENSION_BLACKLIST_VARIABLE, 'nothing');

        $this->expectException(ProcessFileException::class);

        $this->validate('shell.php', '<?php echo 1;', 'document');
    }

    public function testExplicitConstraintsTakePrecedenceOverTheShopPolicy(): void
    {
        // The Smarty back office passes its own policy; it must not be overridden.
        $this->getService(FileProcessorService::class)->validateUpload(
            $this->upload('notes.txt', 'plain text'),
            'image',
            ['text/plain' => ['txt']],
            [],
        );

        $this->expectNotToPerformAssertions();
    }

    /**
     * The call the Twig back office makes: no constraint argument at all. The upload
     * has to be refused before the processor reaches the persistence layer.
     */
    private function process(string $fileName, string $content, string $objectType): void
    {
        $this->getService(FileProcessorService::class)->processFile(
            $this->getService(EventDispatcherInterface::class),
            $this->upload($fileName, $content),
            1,
            'product',
            $objectType,
        );
    }

    private function validate(string $fileName, string $content, string $objectType): void
    {
        $this->getService(FileProcessorService::class)->validateUpload(
            $this->upload($fileName, $content),
            $objectType,
        );
    }

    private function upload(string $fileName, string $content): UploadedFile
    {
        $path = $this->createTestTextFile($content);
        $this->trackFileForCleanup($path);

        return $this->createUploadedFile($path, $fileName, 'application/octet-stream');
    }

    private function pngBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }
}
