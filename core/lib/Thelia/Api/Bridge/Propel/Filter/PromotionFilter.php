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

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Thelia\Api\Bridge\Propel\FrontOperation;
use Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Filters on the promotion flag of a sale element, without telling a visitor what the sale
 * element hides from them.
 *
 * The front read of a sale element whose price the visitor is not shown says it is not on
 * promotion, whatever the catalog holds. A filter on the flag answers the same: for such a
 * visitor those sale elements are never promoted, so they match `false` and never `true`. Asked
 * for every price shown, or on the back office, the flag is the catalog's own and nothing here
 * adds a statement.
 */
final class PromotionFilter extends BooleanFilter
{
    public function __construct(
        private readonly PriceVisibilityResolver $priceVisibilityResolver,
        private readonly CurrentCustomerProvider $currentCustomerProvider,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        parent::__construct($logger, $properties, $nameConverter);
    }

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (null === $value || !$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        $shownClause = $this->shownPriceClause($query, $operation);

        if (null === $shownClause) {
            parent::filterProperty($property, $value, $query, $resourceClass, $operation, $context);

            return;
        }

        $fieldPath = $this->fieldPathOf($property, $query, $context);

        if (filter_var($value, \FILTER_VALIDATE_BOOLEAN)) {
            $query->where($fieldPath.' = ?', true);
            $query->where($shownClause);

            return;
        }

        $name = 'promotion_'.preg_replace('/\W/', '_', $property);

        $query
            ->condition($name.'_flag', $fieldPath.' = ?', false)
            ->condition($name.'_hidden', 'NOT ('.$shownClause.')')
            ->where([$name.'_flag', $name.'_hidden'], Criteria::LOGICAL_OR);
    }

    /**
     * The condition that keeps the products whose price this visitor is shown, null when the
     * request is not a front one, the query is not on products or their sale elements, or
     * every price is shown.
     */
    private function shownPriceClause(ModelCriteria $query, ?Operation $operation): ?string
    {
        if (!FrontOperation::isFront($operation)) {
            return null;
        }

        $productIdColumn = match (true) {
            $query instanceof ProductQuery => ProductTableMap::COL_ID,
            $query instanceof ProductSaleElementsQuery => ProductSaleElementsTableMap::COL_PRODUCT_ID,
            default => null,
        };

        if (null === $productIdColumn) {
            return null;
        }

        return $this->priceVisibilityResolver->shownPriceClause($productIdColumn, $this->currentCustomerProvider->getCurrentCustomer());
    }
}
