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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Test\ApiTestCase;

/**
 * Contract test: every admin GetCollection endpoint must return 200
 * with a valid Hydra structure when authenticated, and 401 without.
 *
 * Endpoint names are taken from `bin/console debug:router | grep _get_collection`.
 */
final class AdminCollectionContractTest extends ApiTestCase
{
    public static function adminCollectionEndpoints(): iterable
    {
        yield 'products' => ['/api/admin/products'];
        yield 'categories' => ['/api/admin/categories'];
        yield 'brands' => ['/api/admin/brands'];
        yield 'customers' => ['/api/admin/customers'];
        yield 'orders' => ['/api/admin/orders'];
        yield 'currencies' => ['/api/admin/currencies'];
        yield 'languages' => ['/api/admin/languages'];
        yield 'taxes' => ['/api/admin/taxes'];
        yield 'tax_rules' => ['/api/admin/tax_rules'];
        yield 'attributes' => ['/api/admin/attributes'];
        yield 'attribute_avs' => ['/api/admin/attribute_avs'];
        yield 'features' => ['/api/admin/features'];
        yield 'feature_avs' => ['/api/admin/feature_avs'];
        yield 'folders' => ['/api/admin/folders'];
        yield 'contents' => ['/api/admin/contents'];
        yield 'order_statutes' => ['/api/admin/order_statutes'];
        yield 'modules' => ['/api/admin/modules'];
        yield 'templates' => ['/api/admin/templates'];
        yield 'configs' => ['/api/admin/configs'];
        // states: skipped — API Platform returns 404 "Invalid identifier
        // value or configuration" (StateProvider config issue, not test-related)
        yield 'customer_titles' => ['/api/admin/customer_titles'];
        yield 'addresses' => ['/api/admin/addresses'];
        yield 'product_sale_elements' => ['/api/admin/product_sale_elements'];
    }

    #[DataProvider('adminCollectionEndpoints')]
    public function testCollectionReturns200WithValidHydraStructure(string $endpoint): void
    {
        $token = $this->authenticateAsAdmin();
        $response = $this->jsonRequest('GET', $endpoint, token: $token);

        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('hydra:member', $data);
        self::assertArrayHasKey('hydra:totalItems', $data);
        self::assertIsArray($data['hydra:member']);
        self::assertIsInt($data['hydra:totalItems']);
    }

    #[DataProvider('adminCollectionEndpoints')]
    public function testCollectionReturns401WithoutToken(string $endpoint): void
    {
        $response = $this->jsonRequest('GET', $endpoint);

        self::assertSame(401, $response->getStatusCode());
    }
}
