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

use Thelia\Model\CatalogRestrictionQuery;

final class CatalogRestrictionApiTest extends PriceListApiTestCase
{
    public function testARuleIsCreatedForACategoryABrandAndAProduct(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup();
        $category = $factory->category();
        $brand = $factory->brand();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency());
        $token = $this->pricingAdminToken();
        $scope = ['customerGroup' => '/api/admin/customer_groups/'.$group->getId()];

        $byCategory = $this->decode($this->jsonRequest('POST', '/api/admin/catalog_restrictions', $scope + ['category' => '/api/admin/categories/'.$category->getId(), 'mode' => 'allow'], $token), 201);
        $byBrand = $this->decode($this->jsonRequest('POST', '/api/admin/catalog_restrictions', $scope + ['brand' => '/api/admin/brands/'.$brand->getId(), 'mode' => 'deny'], $token), 201);
        $byProduct = $this->decode($this->jsonRequest('POST', '/api/admin/catalog_restrictions', $scope + ['product' => '/api/admin/products/'.$product->getId(), 'mode' => 'deny'], $token), 201);

        $row = CatalogRestrictionQuery::create()->findPk($byCategory['id']);
        self::assertSame($category->getId(), $row->getCategoryId());
        self::assertNull($row->getBrandId());
        self::assertNull($row->getProductId());
        self::assertSame('allow', $row->getMode());
        self::assertSame($brand->getId(), CatalogRestrictionQuery::create()->findPk($byBrand['id'])->getBrandId());
        self::assertSame($product->getId(), CatalogRestrictionQuery::create()->findPk($byProduct['id'])->getProductId());

        $read = $this->decode($this->jsonRequest('GET', '/api/admin/catalog_restrictions/'.$byBrand['id'], token: $token));
        self::assertSame('deny', $read['mode']);
        self::assertArrayNotHasKey('category', $read, 'a target the rule does not have is not read');

        $this->decode($this->jsonRequest('PATCH', '/api/admin/catalog_restrictions/'.$byBrand['id'], ['mode' => 'allow'], $token, 'merge-patch+json'));
        self::assertSame('allow', CatalogRestrictionQuery::create()->findPk($byBrand['id'])->getMode());

        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/catalog_restrictions/'.$byProduct['id'], token: $token)->getStatusCode());
    }

    public function testARuleForACustomerIsAccepted(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        $created = $this->decode($this->jsonRequest('POST', '/api/admin/catalog_restrictions', [
            'customer' => '/api/admin/customers/'.$customer->getId(),
            'category' => '/api/admin/categories/'.$factory->category()->getId(),
            'mode' => 'deny',
        ], $this->pricingAdminToken()), 201);

        self::assertSame($customer->getId(), CatalogRestrictionQuery::create()->findPk($created['id'])->getCustomerId());
    }

    public function testARuleWithBothScopesOrNoneIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup();
        $customer = $factory->customer($factory->customerTitle());
        $category = '/api/admin/categories/'.$factory->category()->getId();
        $token = $this->pricingAdminToken();

        $both = $this->jsonRequest('POST', '/api/admin/catalog_restrictions', ['customerGroup' => '/api/admin/customer_groups/'.$group->getId(), 'customer' => '/api/admin/customers/'.$customer->getId(), 'category' => $category, 'mode' => 'allow'], $token);
        self::assertContains('customer', $this->violationPaths($both));

        $none = $this->jsonRequest('POST', '/api/admin/catalog_restrictions', ['category' => $category, 'mode' => 'allow'], $token);
        self::assertContains('customerGroup', $this->violationPaths($none));

        self::assertSame(0, CatalogRestrictionQuery::create()->count());
    }

    public function testARuleWithSeveralTargetsOrNoneIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $scope = ['customerGroup' => '/api/admin/customer_groups/'.$factory->customerGroup()->getId(), 'mode' => 'allow'];
        $token = $this->pricingAdminToken();

        $several = $this->jsonRequest('POST', '/api/admin/catalog_restrictions', $scope + ['category' => '/api/admin/categories/'.$factory->category()->getId(), 'brand' => '/api/admin/brands/'.$factory->brand()->getId()], $token);
        self::assertContains('category', $this->violationPaths($several));

        $none = $this->jsonRequest('POST', '/api/admin/catalog_restrictions', $scope, $token);
        self::assertContains('category', $this->violationPaths($none));

        self::assertSame(0, CatalogRestrictionQuery::create()->count());
    }

    public function testAModeThatIsNeitherAllowNorDenyIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $payload = ['customerGroup' => '/api/admin/customer_groups/'.$factory->customerGroup()->getId(), 'category' => '/api/admin/categories/'.$factory->category()->getId()];
        $token = $this->pricingAdminToken();

        foreach (['', 'ALLOW', 'maybe'] as $mode) {
            self::assertContains('mode', $this->violationPaths($this->jsonRequest('POST', '/api/admin/catalog_restrictions', $payload + ['mode' => $mode], $token)), 'refused: '.$mode);
        }

        self::assertContains('mode', $this->violationPaths($this->jsonRequest('POST', '/api/admin/catalog_restrictions', $payload, $token)));
        self::assertSame(0, CatalogRestrictionQuery::create()->count());
    }

    public function testAProfileWithoutThePricingRightCannotSeeNorWriteTheAssortments(): void
    {
        $factory = $this->createFixtureFactory();
        $payload = ['customerGroup' => '/api/admin/customer_groups/'.$factory->customerGroup()->getId(), 'category' => '/api/admin/categories/'.$factory->category()->getId(), 'mode' => 'deny'];
        $token = $this->catalogOnlyAdminToken();

        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/catalog_restrictions', token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/catalog_restrictions', $payload, $token)->getStatusCode());
        self::assertSame(0, CatalogRestrictionQuery::create()->count());
    }
}
