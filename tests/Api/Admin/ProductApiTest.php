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

use Thelia\Model\ProductQuery;
use Thelia\Test\ApiTestCase;

final class ProductApiTest extends ApiTestCase
{
    public function testCreateProductViaPost(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();

        $response = $this->jsonRequest('POST', '/api/admin/products', [
            'ref' => 'API-PROD-001',
            'visible' => true,
            'virtual' => false,
            'position' => 1,
            'taxRule' => '/api/admin/tax_rules/'.$taxRule->getId(),
            'i18ns' => [
                'en_US' => [
                    'title' => 'API Created Product',
                    'chapo' => 'Short description',
                    'description' => 'Full description',
                    'locale' => 'en_US',
                ],
            ],
            'productCategories' => [
                [
                    'category' => '/api/admin/categories/'.$category->getId(),
                    'defaultCategory' => true,
                ],
            ],
        ], $token);

        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('id', $data);
        self::assertSame('API-PROD-001', $data['ref']);
        self::assertTrue($data['visible']);

        // Verify in DB.
        $product = ProductQuery::create()->findPk($data['id']);
        self::assertNotNull($product);
        self::assertSame('API-PROD-001', $product->getRef());
    }

    public function testGetProductReturnsFullResource(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $response = $this->jsonRequest('GET', '/api/admin/products/'.$product->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        self::assertSame($product->getId(), $data['id']);
        self::assertSame($product->getRef(), $data['ref']);
        self::assertArrayHasKey('productCategories', $data);
        self::assertArrayHasKey('taxRule', $data);
    }

    public function testUpdateProductViaPatch(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'ref' => 'PATCHED-REF',
            'visible' => false,
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);

        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertSame('PATCHED-REF', $reloaded->getRef());
        self::assertSame(0, (int) $reloaded->getVisible());
    }

    public function testDeleteProductRemovesResource(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );
        $productId = $product->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/products/'.$productId, token: $token);

        self::assertSame(204, $response->getStatusCode());
        self::assertNull(ProductQuery::create()->findPk($productId));
    }

    public function testCreateProductWithoutRequiredFieldsReturnsError(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/products', [
            'visible' => true,
        ], $token);

        // Missing ref and taxRule should trigger a validation error (422).
        self::assertSame(422, $response->getStatusCode());
    }

    public function testGetProductReturns404ForNonExistent(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('GET', '/api/admin/products/999999', token: $token);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testCollectionPaginationReturnsCorrectCount(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $factory->product($category, $taxRule, $currency);
        $factory->product($category, $taxRule, $currency);
        $factory->product($category, $taxRule, $currency);

        $response = $this->jsonRequest('GET', '/api/admin/products', token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(3, $response);
    }

    public function testFilterByRefReturnsMatchingProducts(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $factory->product($category, $taxRule, $currency, ['ref' => 'FILTER-TARGET']);
        $factory->product($category, $taxRule, $currency, ['ref' => 'FILTER-OTHER']);

        $response = $this->jsonRequest('GET', '/api/admin/products?ref=FILTER-TARGET', token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);
    }
}
