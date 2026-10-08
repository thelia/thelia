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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Test\IntegrationTestCase;

/**
 * The facets and the data access cache take the product visibility as an optional
 * argument: left without it by the container, the facets would count the products
 * hidden from the visitor and the cache would serve one visitor's catalog to the
 * next, and nothing would fail. The container must hand it to both.
 */
final class ProductVisibilityWiringTest extends IntegrationTestCase
{
    public function testTheListingFacetsAreGivenTheProductVisibility(): void
    {
        self::assertInstanceOf(ProductVisibility::class, $this->productVisibilityOf($this->getService(FilterService::class)));
    }

    public function testTheDataAccessCacheIsGivenTheProductVisibility(): void
    {
        self::assertInstanceOf(ProductVisibility::class, $this->productVisibilityOf($this->getService(ResourceCache::class)));
    }

    /**
     * One memory of the private drops: the visibility the container builds and the one
     * ReservedSaleVisibility hands over to hold the same rule, so that reset() empties it.
     */
    public function testReservedSaleVisibilityHandsOverToTheSharedVisibilityAndRule(): void
    {
        $reservedSaleVisibility = $this->getService(ReservedSaleVisibility::class);

        self::assertSame($this->getService(ProductVisibility::class), $this->propertyOf($reservedSaleVisibility, 'productVisibility'));
        self::assertSame($this->getService(ReservedSaleProductRule::class), $this->propertyOf($reservedSaleVisibility, 'reservedSaleProductRule'));
    }

    /**
     * The data access cache asks on every catalog read whether a module rule is
     * declared: the container hands an iterator that counts without building.
     */
    public function testTheModuleRulesAreCountedWithoutBeingBuilt(): void
    {
        self::assertInstanceOf(\Countable::class, $this->propertyOf($this->getService(ProductVisibility::class), 'moduleRules'));
    }

    private function productVisibilityOf(object $service): mixed
    {
        return $this->propertyOf($service, 'productVisibility');
    }

    private function propertyOf(object $service, string $property): mixed
    {
        return (new \ReflectionProperty($service, $property))->getValue($service);
    }
}
