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

namespace Thelia\Tests\Api\Front;

use Thelia\Model\Product;
use Thelia\Model\ProductImage;
use Thelia\Test\ApiTestCase;

/**
 * The front collection is what the product gallery reads, so decorative and
 * the localized alt text must be part of the payload it gets back.
 */
final class ImageAltTextApiTest extends ApiTestCase
{
    public function testFrontCollectionExposesDecorativeAndAlt(): void
    {
        $product = $this->createProduct();
        $this->createImage($product, decorative: false, alt: 'A leather bag seen from the front');

        $response = $this->jsonRequest('GET', '/api/front/product_images?product.id='.$product->getId());

        self::assertJsonResponseSuccessful($response);

        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('hydra:member', $data);
        self::assertCount(1, $data['hydra:member']);

        $item = $data['hydra:member'][0];
        self::assertFalse($item['decorative']);
        self::assertSame('A leather bag seen from the front', $item['i18ns']['en_US']['alt']);
    }

    public function testFrontCollectionExposesADecorativeImage(): void
    {
        $product = $this->createProduct();
        $this->createImage($product, decorative: true, alt: null);

        $response = $this->jsonRequest('GET', '/api/front/product_images?product.id='.$product->getId());

        self::assertJsonResponseSuccessful($response);

        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $item = $data['hydra:member'][0];
        self::assertTrue($item['decorative']);
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

    private function createImage(Product $product, bool $decorative, ?string $alt): ProductImage
    {
        $image = new ProductImage();
        $image
            ->setProductId($product->getId())
            ->setLocale('en_US')
            ->setTitle('Leather bag')
            ->setVisible(1)
            ->setPosition(1)
            ->setDecorative($decorative ? 1 : 0)
            ->setFile('leather-bag.png');

        if (null !== $alt) {
            $image->setAlt($alt);
        }

        $image->save();

        return $image;
    }
}
