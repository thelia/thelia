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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaAggregatedFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Resource\FilterValue;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * A facet on a flag a product carries through its sale elements — on sale, new.
 *
 * The flag sits on product_sale_elements, never on the product, so a product belongs to
 * the facet as soon as one of its elements raises it. Only a visible element counts: a
 * shopper who checks the box must not be handed a product whose only flagged variant is
 * one the shop never shows.
 *
 * The facet offers a single value, because the question it asks has a single answer worth
 * expressing: an unchecked box adds no condition rather than asking for the products that
 * do not carry the flag.
 */
abstract readonly class AbstractSaleElementFlagFilter implements TheliaFilterInterface, TheliaAggregatedFilterInterface
{
    use SelectedValuesTrait;

    /**
     * The identifier of the only value the facet offers. FilterValue::$id is an int, so the
     * value needs a number rather than a name, and a facet with one value only needs one.
     */
    public const CHECKED_VALUE = 1;

    public function __construct(protected Translator $translator)
    {
    }

    /**
     * The phpName of the product_sale_elements column holding the flag.
     */
    abstract protected static function flagColumn(): string;

    /**
     * The wording of the value, which a checkbox shows as its own label.
     */
    abstract protected function valueTitle(string $locale): string;

    public function getResourceType(): array
    {
        return ['products'];
    }

    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        if (!$this->isChecked($value)) {
            return;
        }

        // Each flag joins the sale elements under its own alias, for two reasons. Two flags
        // checked together must be answerable by two different elements — a product with a
        // discounted variant and a separate new one carries both flags — and a single alias
        // would demand one element raising both. And ProductPriceOrderFilter joins the same
        // table under its own name when the listing is sorted by price.
        $query
            ->useProductSaleElementsQuery(static::joinAlias(), Criteria::INNER_JOIN)
                ->filterByVisible(true)
                ->filterBy(static::flagColumn(), self::CHECKED_VALUE)
            ->endUse();
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        if (!$activeRecord instanceof Product) {
            return null;
        }

        if (!$this->flaggedSaleElements()->filterByProductId($activeRecord->getId())->exists()) {
            return null;
        }

        return [
            (new FilterValue())
                ->setId(self::CHECKED_VALUE)
                ->setTitle($this->valueTitle($locale)),
        ];
    }

    /**
     * How many products of the set carry the flag, counted by the database rather than by
     * asking each product in turn.
     */
    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $flaggedProductIds = $this->flaggedSaleElements()
            ->filterByProductId($resourceIds, Criteria::IN)
            ->select(['ProductId'])
            ->distinct()
            ->find()
            ->getData();

        if ($flaggedProductIds === []) {
            return [];
        }

        return [
            (new FilterValue())
                ->setId(self::CHECKED_VALUE)
                ->setTitle($this->valueTitle($locale))
                ->setCount(\count($flaggedProductIds)),
        ];
    }

    /**
     * The join alias of this flag, distinct from every other filter's.
     */
    protected static function joinAlias(): string
    {
        return static::getFilterName()[0].'_flag_filter_pse';
    }

    private function flaggedSaleElements(): ProductSaleElementsQuery
    {
        return ProductSaleElementsQuery::create()
            ->filterByVisible(true)
            ->filterBy(static::flagColumn(), self::CHECKED_VALUE);
    }

    /**
     * A selection that carries the single value the facet offers. Anything else — an empty
     * group, a forged identifier — leaves the listing alone.
     */
    private function isChecked(mixed $value): bool
    {
        foreach ($this->flattenSelectedValues($value) as $selected) {
            if (is_numeric($selected) && (int) $selected === self::CHECKED_VALUE) {
                return true;
            }
        }

        return false;
    }
}
