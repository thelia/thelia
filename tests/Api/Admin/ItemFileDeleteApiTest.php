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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * Deleting an image or a document over the API takes its files with it, as the back
 * office does: the stored file, and the copy published in the web space to serve it.
 */
final class ItemFileDeleteApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function fileResources(): iterable
    {
        foreach (['product', 'category', 'content', 'folder', 'brand'] as $itemType) {
            yield $itemType.' image' => [$itemType.'_images', $itemType, 'image'];
            yield $itemType.' document' => [$itemType.'_documents', $itemType, 'document'];
        }

        yield 'module image' => ['module_images', 'module', 'image'];
    }

    #[DataProvider('fileResources')]
    public function testDeletingAFileRemovesItsStoredAndPublishedFiles(string $collection, string $itemType, string $fileType): void
    {
        $created = $this->upload($collection, $itemType, $fileType);
        $stored = THELIA_LOCAL_DIR.'media/'.$fileType.'s/'.$itemType.'/'.$created['file'];
        $published = rtrim(THELIA_WEB_DIR, '/').parse_url((string) $created['fileUrl'], \PHP_URL_PATH);
        $this->trackFileForCleanup($stored);
        $this->trackFileForCleanup($published);

        self::assertFileExists($stored);
        self::assertTrue(is_link($published) || file_exists($published), 'The upload publishes no copy.');

        $response = $this->jsonRequest('DELETE', '/api/admin/'.$collection.'/'.$created['id'], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertFileDoesNotExist($stored);
        self::assertFalse(is_link($published) || file_exists($published), 'The published copy stays served.');
    }

    public function testDeletingAnImageRemovesTheFileOfEveryLanguage(): void
    {
        $otherLocale = (string) LangQuery::create()->filterByByDefault(0)->orderByLocale()->findOne()?->getLocale();
        self::assertNotSame('', $otherLocale, 'The test database has a single language.');

        $created = $this->upload('product_images', 'product', 'image');
        $image = ProductImageQuery::create()->findPk($created['id']);
        self::assertNotNull($image);

        $otherFile = 'other-language-'.$created['id'].'.png';
        copy($this->createTestPng(), $image->getUploadDir().DS.$otherFile);
        $image->setLocale($otherLocale)->setFile($otherFile)->save();

        $paths = array_map(static fn (string $file): string => $image->getUploadDir().DS.$file, $image->getStoredFiles());
        array_walk($paths, $this->trackFileForCleanup(...));
        self::assertCount(2, $paths);

        $response = $this->jsonRequest('DELETE', '/api/admin/product_images/'.$created['id'], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        foreach ($paths as $path) {
            self::assertFileDoesNotExist($path);
        }
    }

    /**
     * @return array{id: int, file: string, fileUrl: ?string}
     */
    private function upload(string $collection, string $itemType, string $fileType): array
    {
        $isImage = 'image' === $fileType;

        $this->client->request(
            'POST',
            '/api/admin/'.$collection,
            parameters: [$itemType => (string) $this->createItem($itemType)],
            files: ['fileToUpload' => $this->createUploadedFile(
                $isImage ? $this->createTestPng() : $this->createTestTextFile('%PDF-1.4 placeholder'),
                $isImage ? 'deleted.png' : 'deleted.pdf',
                $isImage ? 'image/png' : 'application/pdf',
            )],
            server: [
                'CONTENT_TYPE' => 'multipart/form-data',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->authenticateAsAdmin(),
            ],
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        /** @var array{id: int, file: string, fileUrl: ?string} $created */
        $created = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $created;
    }

    private function createItem(string $itemType): int
    {
        $factory = $this->createFixtureFactory();

        return match ($itemType) {
            'product' => $factory->product($factory->category(), $factory->taxRule(), $factory->currency())->getId(),
            'category' => $factory->category()->getId(),
            'folder' => $factory->folder()->getId(),
            'content' => $factory->content($factory->folder())->getId(),
            'brand' => $factory->brand()->getId(),
            'module' => (int) ModuleQuery::create()->findOne()?->getId(),
        };
    }
}
