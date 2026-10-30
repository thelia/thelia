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

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * What the administration writes cannot give a bundle a shape the catalogue refuses: the model
 * refuses it whoever writes, and the API answers 422 and not a server error.
 */
final class BundleAdminWritesApiTest extends ApiTestCase
{
    use BuildsBundles;

    public function testABundleCannotReceiveASecondSaleElementThroughTheApi(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $bundle = $this->bundleProduct($factory, $this->service());
        $before = ProductSaleElementsQuery::create()->filterByProductId($bundle->getId())->count();

        $response = $this->jsonRequest('POST', '/api/admin/product_sale_elements', [
            'product' => '/api/admin/products/'.$bundle->getId(),
            'ref' => 'SECOND-'.uniqid(),
            'quantity' => 3,
        ], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($before, ProductSaleElementsQuery::create()->filterByProductId($bundle->getId())->count());
    }

    public function testAPlainProductStillReceivesASecondSaleElementThroughTheApi(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $this->bundleProduct($factory, $this->service());
        $plain = $this->component($factory, '20', 10);

        $response = $this->jsonRequest('POST', '/api/admin/product_sale_elements', [
            'product' => '/api/admin/products/'.$plain->getId(),
            'ref' => 'SECOND-'.uniqid(),
            'quantity' => 3,
        ], $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    }

    private function service(): BundleCompositionService
    {
        return $this->getService(BundleCompositionService::class);
    }
}
