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
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaAggregatedFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaOptionalFilterInterface;
use Thelia\Api\Resource\FilterValue;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Catalog\Product\ProductRatingSourceInterface;
use Thelia\Model\Product;

/**
 * Keeps the products customers rated at least so many stars, out of five.
 *
 * Each value is a threshold and its identifier is its number of stars. The thresholds nest, so
 * checking two of them keeps the products of the lower one: "4 & up" and "2 & up" checked
 * together read as "2 & up".
 *
 * The ratings come from the module that collects reviews, through ProductRatingSourceInterface.
 * Without one the filter is withheld, see TheliaOptionalFilterInterface.
 */
final readonly class RatingFilter implements TheliaFilterInterface, TheliaAggregatedFilterInterface, TheliaOptionalFilterInterface
{
    use SelectedValuesTrait;

    public const THRESHOLDS = [4, 3, 2];

    /**
     * @param iterable<ProductRatingSourceInterface> $sources
     */
    public function __construct(
        private Translator $translator,
        #[AutowireIterator('thelia.catalog.product_rating_source')]
        private iterable $sources = [],
    ) {
    }

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['rating'];
    }

    public function isOffered(): bool
    {
        return $this->source() instanceof ProductRatingSourceInterface;
    }

    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        $source = $this->source();
        $selected = array_values(array_intersect(
            self::THRESHOLDS,
            array_map('intval', array_filter($this->flattenSelectedValues($value), 'is_numeric')),
        ));

        if (!$source instanceof ProductRatingSourceInterface || $selected === []) {
            return;
        }

        $productIds = $source->productIdsRatedAtLeast((float) min($selected));

        if ($productIds === []) {
            $query->where('1 <> 1');

            return;
        }

        $query->filterBy('Id', $productIds, Criteria::IN);
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        if (!$activeRecord instanceof Product) {
            return null;
        }

        $values = $this->getAggregatedValues([(int) $activeRecord->getId()], $locale);

        return $values === [] ? null : $values;
    }

    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array
    {
        $source = $this->source();

        if ($resourceIds === [] || !$source instanceof ProductRatingSourceInterface) {
            return [];
        }

        $resourceIds = array_values(array_unique(array_map('intval', $resourceIds)));
        $values = [];

        foreach (self::THRESHOLDS as $stars) {
            $count = \count(array_intersect(
                $resourceIds,
                array_map('intval', $source->productIdsRatedAtLeast((float) $stars, $resourceIds)),
            ));

            // A threshold no product reaches would narrow the listing to nothing.
            if ($count === 0) {
                continue;
            }

            $values[] = (new FilterValue())
                ->setId($stars)
                ->setTitle($this->translator->trans(id: '%stars% stars & up', parameters: ['%stars%' => $stars], locale: $locale))
                ->setCount($count);
        }

        return $values;
    }

    private function source(): ?ProductRatingSourceInterface
    {
        foreach ($this->sources as $source) {
            return $source;
        }

        return null;
    }
}
