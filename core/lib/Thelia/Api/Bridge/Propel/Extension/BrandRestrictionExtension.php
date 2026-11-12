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

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\FrontOperation;
use Thelia\Api\Resource\Brand;
use Thelia\Api\Resource\BrandDocument;
use Thelia\Api\Resource\BrandImage;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionResolver;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Model\BrandDocumentQuery;
use Thelia\Model\BrandImageQuery;
use Thelia\Model\BrandQuery;
use Thelia\Model\Map\BrandDocumentTableMap;
use Thelia\Model\Map\BrandImageTableMap;
use Thelia\Model\Map\BrandTableMap;

/**
 * Hides from the front callers the brands their catalog restrictions close: a brand a deny
 * rule targets, and, once they have an allow rule, every brand it does not list and that
 * carries no product open to them.
 * The brand, its images and its documents are out of the collection and a 404 on the item,
 * the collection, the item read and the count being bounded by the same rule, as
 * CategoryVisibilityExtension does for the categories.
 *
 * Only brands are narrowed here: the products of a closed brand are out of the catalog
 * through CatalogRestrictionRule, whatever the brand page says. The admin endpoints are left
 * alone: they sit behind ROLE_ADMIN.
 */
final readonly class BrandRestrictionExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    /**
     * @var array<class-string, array{0: class-string<ModelCriteria>, 1: string}>
     */
    private const BRAND_COLUMN_BY_RESOURCE = [
        Brand::class => [BrandQuery::class, BrandTableMap::COL_ID],
        BrandImage::class => [BrandImageQuery::class, BrandImageTableMap::COL_BRAND_ID],
        BrandDocument::class => [BrandDocumentQuery::class, BrandDocumentTableMap::COL_BRAND_ID],
    ];

    public function __construct(
        private CatalogRestrictionResolver $resolver,
        private CurrentCustomerProvider $currentCustomerProvider,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToOpenBrands($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToOpenBrands($query, $resourceClass, $operation);
    }

    private function scopeToOpenBrands(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (!FrontOperation::isFront($operation)) {
            return;
        }

        [$queryClass, $brandIdColumn] = self::BRAND_COLUMN_BY_RESOURCE[$resourceClass] ?? [null, null];

        if (null === $queryClass || !$query instanceof $queryClass) {
            return;
        }

        $this->resolver->applyToBrands($query, $brandIdColumn, $this->currentCustomerProvider->getCurrentCustomer());
    }
}
