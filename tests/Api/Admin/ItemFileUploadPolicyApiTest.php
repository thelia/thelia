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
use Thelia\Core\File\FileConfiguration;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductVideoQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * The API has to apply the same upload policy as the rest of the shop.
 *
 * The document resources declared no constraint at all, so any extension went
 * through; the image resources declared their own mime type list, which neither
 * matched FileConfiguration nor followed the shop configuration.
 */
final class ItemFileUploadPolicyApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        // The database changes are rolled back, the static config cache is not.
        ConfigQuery::resetCache();
        parent::tearDown();
    }

    public function testAServerExecutableDocumentIsRefused(): void
    {
        $product = $this->createProduct();

        $response = $this->upload(
            '/api/admin/product_documents',
            $product,
            $this->createTestTextFile('<?php echo 1;'),
            'shell.php',
        );

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(ProductDocumentQuery::create()->filterByProductId($product->getId())->findOne());
    }

    public function testABlacklistedDocumentExtensionIsRefused(): void
    {
        $product = $this->createProduct();

        $response = $this->upload(
            '/api/admin/product_documents',
            $product,
            $this->createTestTextFile('MZ'),
            'payload.exe',
        );

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(ProductDocumentQuery::create()->filterByProductId($product->getId())->findOne());
    }

    public function testANonImageIsRefusedAsAnImage(): void
    {
        $product = $this->createProduct();

        $response = $this->upload(
            '/api/admin/product_images',
            $product,
            $this->createTestTextFile('not an image'),
            'kitten.png',
        );

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(ProductImageQuery::create()->filterByProductId($product->getId())->findOne());
    }

    /**
     * The list is the shop's, not the resource's: narrowing it in the
     * configuration has to reach the API.
     */
    public function testTheShopConfigurationNarrowsTheAcceptedImageMimeTypes(): void
    {
        ConfigQuery::write(FileConfiguration::IMAGE_MIME_TYPES_VARIABLE, 'image/png');

        $product = $this->createProduct();

        $response = $this->upload(
            '/api/admin/product_images',
            $product,
            $this->createTestTextFile('GIF89a'.str_repeat("\x00", 16)),
            'kitten.gif',
        );

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * A video carries a real mime type and a real extension, so the double
     * extension is the way past the type check: the floor has to catch the `php`
     * segment wherever it sits in the name.
     */
    public function testAServerExecutableVideoNameIsRefused(): void
    {
        $product = $this->createProduct();

        $response = $this->upload(
            '/api/admin/product_videos/upload',
            $product,
            $this->createTestMp4(),
            'shell.php.mp4',
        );

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(ProductVideoQuery::create()->filterByProductId($product->getId())->findOne());
    }

    public function testANonVideoIsRefusedAsAVideo(): void
    {
        $product = $this->createProduct();

        $response = $this->upload(
            '/api/admin/product_videos/upload',
            $product,
            $this->createTestTextFile('not a video'),
            'assembly.mp4',
        );

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(ProductVideoQuery::create()->filterByProductId($product->getId())->findOne());
    }

    /**
     * The smallest file a mime type guesser reads as an MP4: an ftyp box and
     * nothing after it.
     */
    private function createTestMp4(): string
    {
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('thelia_test_video_').'.mp4';
        file_put_contents(
            $path,
            "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41\x00\x00\x00\x08free".str_repeat("\x00", 64),
        );
        $this->trackFileForCleanup($path);

        return $path;
    }

    public function testALegitimateUploadStillGoesThrough(): void
    {
        $product = $this->createProduct();

        $response = $this->upload(
            '/api/admin/product_images',
            $product,
            $this->createTestPng(),
            'kitten.png',
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $image = ProductImageQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($image);
        $this->trackFileForCleanup($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile());
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

    private function upload(string $uri, Product $product, string $path, string $fileName): Response
    {
        $this->client->request(
            'POST',
            $uri,
            parameters: ['product' => (string) $product->getId()],
            // The mime type the client claims is deliberately not the one the file
            // holds: the policy sniffs the content, it does not trust the request.
            files: ['fileToUpload' => $this->createUploadedFile($path, $fileName, 'image/png')],
            server: [
                'CONTENT_TYPE' => 'multipart/form-data',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->authenticateAsAdmin(),
            ],
        );

        return $this->client->getResponse();
    }
}
