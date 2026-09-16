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
use Thelia\Action\ProductVideo as ProductVideoAction;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsProductVideoQuery;
use Thelia\Model\ProductVideoQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * Attaching a video to a product over the admin API.
 *
 * The address a merchant pastes is a write-only field: what comes back is the
 * platform, the identifier, and the frame address built from them. The test
 * asserts the address itself is nowhere in the response, because that is the
 * whole point of resolving it server side.
 */
final class ProductVideoApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testAPlatformVideoKeepsOnlyItsIdentifier(): void
    {
        $product = $this->createProduct();

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/product_videos',
            [
                'product' => '/api/admin/products/'.$product->getId(),
                'url' => 'https://youtu.be/dQw4w9WgXcQ?t=42',
                'visible' => true,
                'i18ns' => ['en_US' => ['title' => 'Demo', 'alt' => 'A demonstration of the bag']],
            ],
            $this->authenticateAsAdmin(),
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $body = (string) $response->getContent();
        $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('youtube', $payload['provider']);
        self::assertSame('dQw4w9WgXcQ', $payload['externalId']);
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $payload['embedUrl']);
        self::assertNull($payload['fileUrl']);
        self::assertStringNotContainsString('youtu.be', $body, 'The pasted address must not travel back.');
        self::assertArrayNotHasKey('url', $payload);

        $video = ProductVideoQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($video);
        self::assertSame('dQw4w9WgXcQ', $video->getExternalId());
        self::assertSame('A demonstration of the bag', $video->setLocale('en_US')->getAlt());
    }

    public function testAnAddressOfNoKnownPlatformIsRefusedAndNamesTheOnesAccepted(): void
    {
        $product = $this->createProduct();

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/product_videos',
            [
                'product' => '/api/admin/products/'.$product->getId(),
                'url' => 'https://videos.example.com/watch?v=dQw4w9WgXcQ',
            ],
            $this->authenticateAsAdmin(),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('YouTube', (string) $response->getContent());
        self::assertStringContainsString('Vimeo', (string) $response->getContent());
        self::assertNull(ProductVideoQuery::create()->filterByProductId($product->getId())->findOne());
    }

    public function testAHostedVideoIsUploaded(): void
    {
        $product = $this->createProduct();

        $this->client->request(
            'POST',
            '/api/admin/product_videos/upload',
            parameters: [
                'product' => (string) $product->getId(),
                'i18ns' => json_encode(['en_US' => ['title' => 'Assembly']], \JSON_THROW_ON_ERROR),
            ],
            files: ['fileToUpload' => $this->createUploadedFile(
                $this->createTestMp4(),
                'assembly.mp4',
                'video/mp4',
            )],
            server: [
                'CONTENT_TYPE' => 'multipart/form-data',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->authenticateAsAdmin(),
            ],
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $video = ProductVideoQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($video);
        self::assertSame('file', $video->getProvider());

        $path = $video->getUploadDir().\DIRECTORY_SEPARATOR.$video->getFile();
        $this->trackFileForCleanup($path);
        self::assertFileExists($path);

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('file', $payload['provider']);
        self::assertNull($payload['externalId']);
        self::assertNull($payload['embedUrl']);

        // The library sits outside the web root: what a visitor is handed is the
        // link published under the cache directory, never the stored file.
        self::assertStringContainsString('/cache/videos/product/', (string) $payload['fileUrl']);
        self::assertStringNotContainsString('/local/', (string) $payload['fileUrl']);
        $this->trackFileForCleanup($this->getService(ProductVideoAction::class)->cachedFilePath($video));
    }

    public function testACombinationIsGivenAVideoAndTakesItBack(): void
    {
        $product = $this->createProduct();
        $factory = $this->createFixtureFactory();
        $video = $factory->productVideo($product);
        $combination = $factory->productSaleElement($product);

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/product_sale_elements_product_video',
            [
                'productSaleElementsId' => $combination->getId(),
                'productVideoId' => $video->getId(),
            ],
            $this->authenticateAsAdmin(),
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $link = ProductSaleElementsProductVideoQuery::create()
            ->filterByProductVideoId($video->getId())
            ->findOne();
        self::assertNotNull($link);

        $response = $this->jsonRequest(
            'DELETE',
            '/api/admin/product_sale_elements_product_video/'.$link->getId(),
            token: $this->authenticateAsAdmin(),
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(ProductSaleElementsProductVideoQuery::create()->findPk($link->getId()));
    }

    public function testTheSameVideoIsNotGivenTwiceToACombination(): void
    {
        $product = $this->createProduct();
        $factory = $this->createFixtureFactory();
        $video = $factory->productVideo($product);
        $combination = $factory->productSaleElement($product);

        $payload = [
            'productSaleElementsId' => $combination->getId(),
            'productVideoId' => $video->getId(),
        ];

        $first = $this->jsonRequest('POST', '/api/admin/product_sale_elements_product_video', $payload, $this->authenticateAsAdmin());
        self::assertSame(Response::HTTP_CREATED, $first->getStatusCode(), (string) $first->getContent());

        $second = $this->jsonRequest('POST', '/api/admin/product_sale_elements_product_video', $payload, $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $second->getStatusCode(), (string) $second->getContent());
        self::assertCount(
            1,
            ProductSaleElementsProductVideoQuery::create()->filterByProductVideoId($video->getId())->find(),
        );
    }

    public function testAVideoIsRewordedGivenAThumbnailAndHidden(): void
    {
        $product = $this->createProduct();
        $video = $this->createFixtureFactory()->productVideo($product);
        $image = $this->createFixtureFactory()->productImage($product);
        $this->trackFileForCleanup($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile());

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/product_videos/'.$video->getId(),
            [
                'thumbnailImage' => '/api/admin/product_images/'.$image->getId(),
                'visible' => false,
                'i18ns' => ['en_US' => ['title' => 'Reworded', 'alt' => 'The bag, shown from every side']],
            ],
            $this->authenticateAsAdmin(),
            'merge-patch+json',
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $reloaded = ProductVideoQuery::create()->findPk($video->getId());
        self::assertNotNull($reloaded);
        self::assertSame($image->getId(), $reloaded->getThumbnailImageId());
        self::assertSame(0, $reloaded->getVisible());
        self::assertSame('Reworded', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame('The bag, shown from every side', $reloaded->setLocale('en_US')->getAlt());
    }

    public function testAVideoIsDeleted(): void
    {
        $product = $this->createProduct();
        $video = $this->createFixtureFactory()->productVideo($product);

        $response = $this->jsonRequest(
            'DELETE',
            '/api/admin/product_videos/'.$video->getId(),
            token: $this->authenticateAsAdmin(),
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(ProductVideoQuery::create()->findPk($video->getId()));
    }

    public function testTheAdminCollectionIsClosedToAnAnonymousCaller(): void
    {
        $response = $this->jsonRequest('GET', '/api/admin/product_videos');

        self::assertContains(
            $response->getStatusCode(),
            [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
            (string) $response->getContent(),
        );
    }

    /**
     * The smallest file a mime type guesser reads as an MP4: an ftyp box and
     * nothing after it. The shop checks the type it guesses, not the one the
     * client declares.
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

    private function createProduct(): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );
    }
}
