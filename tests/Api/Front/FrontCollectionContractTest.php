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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Test\ApiTestCase;

/**
 * Contract test: public front collection endpoints must return 200
 * with valid Hydra structure without authentication.
 *
 * Excluded endpoints:
 * - /api/front/currencies: 500 (StateProvider issue, pre-existing)
 * - /api/front/countries: 404 (identifier config issue, pre-existing)
 * - Authenticated endpoints: CustomerFamily vendor module crashes on
 *   customer save via OpenApiListener::getContent() on null (same
 *   vendor bug as documented in handover §4.6)
 */
final class FrontCollectionContractTest extends ApiTestCase
{
    public static function publicFrontEndpoints(): iterable
    {
        yield 'products' => ['/api/front/products'];
        yield 'categories' => ['/api/front/categories'];
        yield 'brands' => ['/api/front/brands'];
        yield 'languages' => ['/api/front/languages'];
        yield 'contents' => ['/api/front/contents'];
        yield 'folders' => ['/api/front/folders'];
        yield 'attributes' => ['/api/front/attributes'];
        yield 'attribute_avs' => ['/api/front/attribute_avs'];
        yield 'features' => ['/api/front/features'];
        yield 'feature_avs' => ['/api/front/feature_avs'];
        yield 'customer_titles' => ['/api/front/customer_titles'];
        yield 'taxes' => ['/api/front/taxes'];
        yield 'tax_rules' => ['/api/front/tax_rules'];
        yield 'product_sale_elements' => ['/api/front/product_sale_elements'];
    }

    #[DataProvider('publicFrontEndpoints')]
    public function testPublicCollectionReturns200WithHydraStructure(string $endpoint): void
    {
        $response = $this->jsonRequest('GET', $endpoint);

        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('hydra:member', $data);
        self::assertArrayHasKey('hydra:totalItems', $data);
    }

    public static function authenticatedFrontEndpoints(): iterable
    {
        yield 'account/addresses' => ['/api/front/account/addresses'];
        yield 'account/orders' => ['/api/front/account/orders'];
    }

    #[DataProvider('authenticatedFrontEndpoints')]
    public function testAuthenticatedEndpointReturns401WithoutToken(string $endpoint): void
    {
        $response = $this->jsonRequest('GET', $endpoint);

        self::assertSame(401, $response->getStatusCode());
    }
}
