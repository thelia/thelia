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

use Thelia\Test\ApiTestCase;

/**
 * Tests for the public front catalog API endpoints.
 * These do NOT require authentication.
 */
final class FrontCatalogApiTest extends ApiTestCase
{
    public function testGetProductByIdReturnsResource(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $response = $this->jsonRequest('GET', '/api/front/products/'.$product->getId());

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($product->getId(), $data['id']);
        self::assertSame($product->getRef(), $data['ref']);
        self::assertArrayHasKey('productSaleElements', $data);
    }

    public function testGetCategoryByIdReturnsResource(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();

        $response = $this->jsonRequest('GET', '/api/front/categories/'.$category->getId());

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($category->getId(), $data['id']);
    }

    public function testGetBrandByIdReturnsResource(): void
    {
        $factory = $this->createFixtureFactory();
        $brand = $factory->brand(['title' => 'Front Brand']);

        $response = $this->jsonRequest('GET', '/api/front/brands/'.$brand->getId());

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($brand->getId(), $data['id']);
    }

    public function testGetContentByIdReturnsResource(): void
    {
        $factory = $this->createFixtureFactory();
        $content = $factory->content($factory->folder());

        $response = $this->jsonRequest('GET', '/api/front/contents/'.$content->getId());

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($content->getId(), $data['id']);
    }

    public function testGetFolderByIdReturnsResource(): void
    {
        $factory = $this->createFixtureFactory();
        $folder = $factory->folder();

        $response = $this->jsonRequest('GET', '/api/front/folders/'.$folder->getId());

        self::assertJsonResponseSuccessful($response);
    }

    public function testProductCollectionSearchByRef(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $factory->product($category, $taxRule, $currency, ['ref' => 'FRONT-FIND-ME', 'visible' => 1]);
        $factory->product($category, $taxRule, $currency, ['ref' => 'FRONT-OTHER', 'visible' => 1]);

        $response = $this->jsonRequest('GET', '/api/front/products?ref=FRONT-FIND-ME');

        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);
    }

    public function testProductReturns404ForNonExistent(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/products/999999');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testFrontProductsDoNotExposeAdminWriteFields(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $response = $this->jsonRequest('GET', '/api/front/products/'.$product->getId());
        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        // Front read must NOT expose admin-only fields.
        self::assertArrayNotHasKey('template', $data);
    }
}
