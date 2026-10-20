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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\BrandImageQuery;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * Deleting an item through the admin API deletes the files of its images and
 * documents, as the back office does.
 */
final class DeletedItemMediaApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testDeletingAProductDeletesTheFilesOfItsImagesAndDocuments(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $productId = $product->getId();

        $this->upload('/api/admin/product_images', ['product' => (string) $productId], $this->createTestPng(), 'api-deleted.png', 'image/png');
        $this->upload('/api/admin/product_documents', ['product' => (string) $productId, 'i18ns' => '{}'], $this->createTestTextFile('%PDF-1.4'), 'api-deleted.pdf', 'application/pdf');

        $image = ProductImageQuery::create()->findOneByProductId($productId);
        $document = ProductDocumentQuery::create()->findOneByProductId($productId);
        self::assertNotNull($image);
        self::assertNotNull($document);
        $imageFile = $this->tracked($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile());
        $documentFile = $this->tracked($document->getUploadDir().\DIRECTORY_SEPARATOR.$document->getFile());
        self::assertFileExists($imageFile);
        self::assertFileExists($documentFile);

        $response = $this->jsonRequest('DELETE', '/api/admin/products/'.$productId, token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertFileDoesNotExist($imageFile);
        self::assertFileDoesNotExist($documentFile);
    }

    public function testDeletingABrandDeletesTheFilesOfItsImages(): void
    {
        $brandId = $this->createFixtureFactory()->brand()->getId();

        $this->upload('/api/admin/brand_images', ['brand' => (string) $brandId], $this->createTestPng(), 'api-deleted-brand.png', 'image/png');

        $image = BrandImageQuery::create()->findOneByBrandId($brandId);
        self::assertNotNull($image);
        $imageFile = $this->tracked($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile());
        self::assertFileExists($imageFile);

        $response = $this->jsonRequest('DELETE', '/api/admin/brands/'.$brandId, token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertFileDoesNotExist($imageFile);
    }

    private function tracked(string $path): string
    {
        // Removed by the test when the code under test leaves it behind.
        $this->trackFileForCleanup($path);

        return $path;
    }

    /**
     * @param array<string, string> $fields
     */
    private function upload(string $uri, array $fields, string $path, string $fileName, string $mimeType): void
    {
        $this->client->request(
            'POST',
            $uri,
            parameters: $fields,
            files: ['fileToUpload' => $this->createUploadedFile($path, $fileName, $mimeType)],
            server: [
                'CONTENT_TYPE' => 'multipart/form-data',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->authenticateAsAdmin(),
            ],
        );

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
    }
}
