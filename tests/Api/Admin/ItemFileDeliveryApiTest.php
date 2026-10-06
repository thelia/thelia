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
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductImage;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * The file endpoints answer from the shop origin: a document is handed over as a
 * download, so that whatever the shop accepted as one never opens as a page of the
 * shop, while an image is still shown in place.
 */
final class ItemFileDeliveryApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testADocumentIsServedAsADownload(): void
    {
        $document = new ProductDocument();
        $document
            ->setProductId($this->createProductId())
            ->setFile('delivery-test.pdf')
            ->setVisible(1)
            ->setPosition(1)
            ->save();
        $this->storeMediaFile($document->getUploadDir(), 'delivery-test.pdf', '%PDF-1.4');

        $response = $this->requestFile('/api/admin/product_documents/'.$document->getId().'/file');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('attachment; filename=delivery-test.pdf', $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testAnImageIsStillServedInPlace(): void
    {
        $image = new ProductImage();
        $image
            ->setProductId($this->createProductId())
            ->setFile('delivery-test.png')
            ->setVisible(1)
            ->setPosition(1)
            ->save();
        $png = $this->createTestPng();
        $this->trackFileForCleanup($png);
        $this->storeMediaFile($image->getUploadDir(), 'delivery-test.png', (string) file_get_contents($png));

        $response = $this->requestFile('/api/admin/product_images/'.$image->getId().'/file');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNull($response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    private function requestFile(string $uri): Response
    {
        $this->client->request('GET', $uri, server: [
            'HTTP_ACCEPT' => '*/*',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->authenticateAsAdmin(),
        ]);

        return $this->client->getResponse();
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

    private function storeMediaFile(string $directory, string $fileName, string $content): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        $path = $directory.\DIRECTORY_SEPARATOR.$fileName;
        file_put_contents($path, $content);
        $this->trackFileForCleanup($path);
    }
}
