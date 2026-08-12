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

namespace Thelia\Tests\Api\Admin;

use Thelia\Core\File\FileConfiguration;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductImage;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * A client building an upload form has to be able to ask what the shop accepts,
 * rather than restate the lists and drift away from what the server enforces.
 */
final class FileUploadConstraintsApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        // The database changes are rolled back, the static config cache is not.
        ConfigQuery::resetCache();
        parent::tearDown();
    }

    public function testAnImageResourcePublishesTheAcceptedMimeTypes(): void
    {
        $constraints = $this->readConstraints($this->createImage());

        self::assertSame(FileConfiguration::DEFAULT_IMAGE_MIME_TYPES, $constraints['allowedMimeTypes']);
        self::assertContains('jpg', $constraints['allowedExtensions']);
        self::assertContains('svg', $constraints['allowedExtensions']);
        // The executable floor applies to images too, whatever the configuration says.
        self::assertContains('php', $constraints['forbiddenExtensions']);
    }

    public function testADocumentResourcePublishesTheForbiddenExtensions(): void
    {
        $constraints = $this->readConstraints($this->createDocument());

        // A document is constrained by extension, not by mime type.
        self::assertSame([], $constraints['allowedMimeTypes']);
        self::assertSame([], $constraints['allowedExtensions']);
        self::assertContains('exe', $constraints['forbiddenExtensions']);
        self::assertContains('php', $constraints['forbiddenExtensions']);
    }

    public function testWhatIsPublishedFollowsTheShopConfiguration(): void
    {
        ConfigQuery::write(FileConfiguration::IMAGE_MIME_TYPES_VARIABLE, 'image/png, image/avif');

        $constraints = $this->readConstraints($this->createImage());

        self::assertSame(['image/png', 'image/avif'], $constraints['allowedMimeTypes']);
        self::assertSame(['png', 'avif'], $constraints['allowedExtensions']);
    }

    /**
     * @return array{allowedMimeTypes: list<string>, allowedExtensions: list<string>, forbiddenExtensions: list<string>}
     */
    private function readConstraints(string $uri): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);

        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('uploadConstraints', $data, (string) $response->getContent());

        return $data['uploadConstraints'];
    }

    private function createImage(): string
    {
        $image = new ProductImage();
        $image
            ->setProductId($this->createProductId())
            ->setFile('constraints-test.png')
            ->setVisible(1)
            ->setPosition(1)
            ->save();

        $this->storeMediaFile($image->getUploadDir(), 'constraints-test.png', $this->onePixelPng());

        return '/api/admin/product_images/'.$image->getId();
    }

    private function createDocument(): string
    {
        $document = new ProductDocument();
        $document
            ->setProductId($this->createProductId())
            ->setFile('constraints-test.pdf')
            ->setVisible(1)
            ->setPosition(1)
            ->save();

        $this->storeMediaFile($document->getUploadDir(), 'constraints-test.pdf', '%PDF-1.4');

        return '/api/admin/product_documents/'.$document->getId();
    }

    private function createProductId(): int
    {
        $factory = $this->createFixtureFactory();

        return $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        )->getId();
    }

    /**
     * The resource carries a file url, which is computed by processing the stored
     * file, so that file has to be on disk.
     */
    private function storeMediaFile(string $directory, string $fileName, string $content): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        $path = $directory.\DIRECTORY_SEPARATOR.$fileName;
        file_put_contents($path, $content);
        $this->trackFileForCleanup($path);
    }

    private function onePixelPng(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }
}
