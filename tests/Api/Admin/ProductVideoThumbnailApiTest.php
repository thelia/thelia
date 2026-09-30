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
use Thelia\Model\ProductVideoQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * The thumbnail of a video is one of the images of its own product: the back
 * office only offers those, and the admin API holds the same rule, or the product
 * sheet shows the picture of another product in front of the video.
 */
final class ProductVideoThumbnailApiTest extends ApiTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testAVideoIsNotCreatedWithTheImageOfAnotherProductAsItsThumbnail(): void
    {
        $product = $this->createProduct();
        $foreignImage = $this->createFixtureFactory()->productImage($this->createProduct());
        $this->trackFileForCleanup($foreignImage->getUploadDir().\DIRECTORY_SEPARATOR.$foreignImage->getFile());

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/product_videos',
            [
                'product' => '/api/admin/products/'.$product->getId(),
                'url' => 'https://youtu.be/dQw4w9WgXcQ',
                'thumbnailImage' => '/api/admin/product_images/'.$foreignImage->getId(),
                'visible' => true,
            ],
            $this->authenticateAsAdmin(),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(
            ProductVideoQuery::create()->filterByProductId($product->getId())->findOne(),
            'Nothing is stored when the thumbnail belongs to another product.',
        );
    }

    public function testAVideoIsNotGivenTheImageOfAnotherProductAsItsThumbnail(): void
    {
        $product = $this->createProduct();
        $video = $this->createFixtureFactory()->productVideo($product);
        $foreignImage = $this->createFixtureFactory()->productImage($this->createProduct());
        $this->trackFileForCleanup($foreignImage->getUploadDir().\DIRECTORY_SEPARATOR.$foreignImage->getFile());

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/product_videos/'.$video->getId(),
            ['thumbnailImage' => '/api/admin/product_images/'.$foreignImage->getId()],
            $this->authenticateAsAdmin(),
            'merge-patch+json',
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(ProductVideoQuery::create()->findPk($video->getId())?->getThumbnailImageId());
    }

    private function createProduct(): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
    }
}
