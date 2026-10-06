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

namespace Thelia\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Product;
use Thelia\Model\ProductDocument;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * The file of an image or a document is served whatever the caller accepts. A browser
 * opening the address asks for HTML first: content negotiation then picked the API
 * documentation page, and the request ended in a 500.
 */
final class BinaryFileAcceptApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    private const BROWSER_ACCEPT = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8';

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function fileRoutes(): iterable
    {
        foreach (['front' => false, 'admin' => true] as $scope => $asAdmin) {
            yield $scope.' image, html' => [$scope.'/product_images', $asAdmin, 'text/html'];
            yield $scope.' image, browser' => [$scope.'/product_images', $asAdmin, self::BROWSER_ACCEPT];
            yield $scope.' document, html' => [$scope.'/product_documents', $asAdmin, 'text/html'];
            yield $scope.' document, browser' => [$scope.'/product_documents', $asAdmin, self::BROWSER_ACCEPT];
        }
    }

    #[DataProvider('fileRoutes')]
    public function testTheFileIsServedToACallerAskingForHtml(string $collection, bool $asAdmin, string $accept): void
    {
        $product = $this->createProduct();
        $id = str_ends_with($collection, 'images') ? $this->createImage($product) : $this->createDocument($product);

        $response = $this->requestFile('/api/'.$collection.'/'.$id.'/file', $accept, $asAdmin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertInstanceOf(BinaryFileResponse::class, $response);
    }

    public function testAnAcceptNoFormatMatchesIsRefusedRatherThanFailing(): void
    {
        $id = $this->createDocument($this->createProduct());

        $response = $this->requestFile('/api/front/product_documents/'.$id.'/file', 'application/x-unknown', false);

        self::assertSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
    }

    private function requestFile(string $uri, string $accept, bool $asAdmin): Response
    {
        $server = ['HTTP_ACCEPT' => $accept];

        if ($asAdmin) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$this->authenticateAsAdmin();
        }

        $this->client->request('GET', $uri, server: $server);

        return $this->client->getResponse();
    }

    private function createProduct(): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
    }

    private function createImage(Product $product): int
    {
        $image = $this->createFixtureFactory()->productImage($product);
        $this->trackFileForCleanup($image->getUploadDir().DS.$image->getFile());

        return $image->getId();
    }

    private function createDocument(Product $product): int
    {
        $document = new ProductDocument();
        $document->setProductId($product->getId())->setVisible(1)->setFile('accept-'.uniqid().'.pdf')->save();

        $path = $document->getUploadDir().DS.$document->getFile();

        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0o775, true);
        }

        file_put_contents($path, '%PDF-1.4 placeholder');
        $this->trackFileForCleanup($path);

        return $document->getId();
    }
}
