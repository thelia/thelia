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

namespace Thelia\Domain\Search\Provider;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionResolver;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Model\BrandI18nQuery;
use Thelia\Model\BrandQuery;
use Thelia\Model\Map\BrandTableMap;

final class BrandDocumentProvider extends TranslatedCatalogueDocumentProvider
{
    public const string FAMILY = 'brand';

    public function __construct(
        private readonly ?CatalogRestrictionResolver $catalogRestrictionResolver = null,
        private readonly ?CurrentCustomerProvider $currentCustomerProvider = null,
    ) {
    }

    public function family(): string
    {
        return self::FAMILY;
    }

    protected function entityQuery(): ModelCriteria
    {
        return BrandQuery::create();
    }

    protected function i18nQuery(): ModelCriteria
    {
        return BrandI18nQuery::create();
    }

    protected function narrowToTheVisitor(ModelCriteria $query): void
    {
        if (null === $this->catalogRestrictionResolver || null === $this->currentCustomerProvider) {
            return;
        }

        $this->catalogRestrictionResolver->applyToBrands($query, BrandTableMap::COL_ID, $this->currentCustomerProvider->getCurrentCustomer());
    }
}
