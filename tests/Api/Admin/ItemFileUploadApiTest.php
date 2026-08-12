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
use Thelia\Model\Product;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * The documented way to attach a file to an item over the API.
 *
 * The item the file belongs to is not part of any uri template, so it travels in
 * the multipart body. Reading it from the route attributes only made every one of
 * these endpoints answer 500 whatever the client sent.
 */
final class ItemFileUploadApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testUploadADocumentWithThePlainProductId(): void
    {
        $product = $this->createProduct();

        $response = $this->upload(
            '/api/admin/product_documents',
            ['product' => (string) $product->getId(), 'i18ns' => '{}'],
            $this->createTestTextFile('%PDF-1.4 placeholder'),
            'manual.pdf',
            'application/pdf',
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $document = ProductDocumentQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($document);
        self::assertSame(1, (int) $document->getVisible());

        $path = $document->getUploadDir().\DIRECTORY_SEPARATOR.$document->getFile();
        $this->trackFileForCleanup($path);
        self::assertFileExists($path);
    }

    public function testUploadADocumentWithTheProductIri(): void
    {
        $product = $this->createProduct();

        $response = $this->upload(
            '/api/admin/product_documents',
            ['product' => '/api/admin/products/'.$product->getId()],
            $this->createTestTextFile('plain notes'),
            'notes.txt',
            'text/plain',
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $document = ProductDocumentQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($document);
        $this->trackFileForCleanup($document->getUploadDir().\DIRECTORY_SEPARATOR.$document->getFile());
    }

    public function testUploadAnImage(): void
    {
        $product = $this->createProduct();

        $response = $this->upload(
            '/api/admin/product_images',
            ['product' => (string) $product->getId()],
            $this->createTestPng(),
            'kitten.png',
            'image/png',
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $image = ProductImageQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($image);
        $this->trackFileForCleanup($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile());
    }

    public function testAnUploadWithoutItsItemIsRefused(): void
    {
        $response = $this->upload(
            '/api/admin/product_documents',
            [],
            $this->createTestTextFile('orphan'),
            'orphan.txt',
            'text/plain',
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
    }

    private function createProduct(): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );
    }

    /**
     * @param array<string, string> $fields
     */
    private function upload(
        string $uri,
        array $fields,
        string $path,
        string $fileName,
        string $mimeType,
    ): Response {
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

        return $this->client->getResponse();
    }
}
