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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsProductVideo;
use Thelia\Test\ApiTestCase;

/**
 * What the front endpoints hand out, and what they hold back.
 *
 * The filtering is the shop's, not the caller's: a hidden video, or one hanging
 * off a product taken offline, is out of reach whether or not the theme passes
 * `visible=true`.
 */
final class ProductVideoApiTest extends ApiTestCase
{
    public function testTheCollectionOfAProductComesInPositionOrder(): void
    {
        $product = $this->createProduct();
        $factory = $this->createFixtureFactory();

        $first = $factory->productVideo($product, ['externalId' => 'aaaaaaaaaaa']);
        $second = $factory->productVideo($product, ['externalId' => 'bbbbbbbbbbb']);

        $videos = $this->collection('/api/front/product_videos?product.id='.$product->getId().'&order[position]=asc');

        self::assertCount(2, $videos);
        self::assertSame($first->getId(), $videos[0]['id']);
        self::assertSame($second->getId(), $videos[1]['id']);
        self::assertSame('https://www.youtube-nocookie.com/embed/aaaaaaaaaaa', $videos[0]['embedUrl']);
        self::assertNull($videos[0]['fileUrl']);
    }

    public function testAHiddenVideoIsOutOfReachWithoutTheCallerAskingForIt(): void
    {
        $product = $this->createProduct();
        $factory = $this->createFixtureFactory();

        $visible = $factory->productVideo($product, ['externalId' => 'aaaaaaaaaaa']);
        $hidden = $factory->productVideo($product, ['externalId' => 'bbbbbbbbbbb', 'visible' => 0]);

        $videos = $this->collection('/api/front/product_videos?product.id='.$product->getId());

        self::assertSame([$visible->getId()], array_column($videos, 'id'));

        $response = $this->jsonRequest('GET', '/api/front/product_videos/'.$hidden->getId());
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAVideoOfAnOfflineProductIsOutOfReach(): void
    {
        $factory = $this->createFixtureFactory();
        $offlineProduct = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
            ['visible' => 0],
        );
        $video = $factory->productVideo($offlineProduct);

        self::assertSame([], $this->collection('/api/front/product_videos?product.id='.$offlineProduct->getId()));

        $response = $this->jsonRequest('GET', '/api/front/product_videos/'.$video->getId());
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTheVideosOfACombinationAreReadByProduct(): void
    {
        $product = $this->createProduct();
        $factory = $this->createFixtureFactory();
        $video = $factory->productVideo($product);
        $combination = $factory->productSaleElement($product);

        $link = new ProductSaleElementsProductVideo();
        $link
            ->setProductSaleElementsId($combination->getId())
            ->setProductVideoId($video->getId())
            ->save();

        $links = $this->collection(
            '/api/front/product_sale_elements_product_video?productSaleElements.product.id='.$product->getId(),
        );

        self::assertCount(1, $links);
        self::assertSame($combination->getId(), $links[0]['productSaleElementsId']);
        self::assertSame($video->getId(), $links[0]['productVideoId']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collection(string $uri): array
    {
        $response = $this->jsonRequest('GET', $uri);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $payload['hydra:member'] ?? $payload['member'] ?? [];
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
