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

namespace Thelia\Domain\Pricing;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundlePrice;
use Thelia\Domain\Catalog\Bundle\BundlePriceCalculator;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Model\Currency;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The effective price of the sale elements of the bundles priced by their components:
 * the full price of the components, the price less the discount of the bundle, and
 * nothing else. No catalog rule and no operation prices such a sale element: the price
 * of a bundle is made of the prices of its components, and a rule on the sale element
 * that stands for the bundle would price a number that is nowhere.
 *
 * The sale elements of a page are asked nothing while the shop holds no such bundle
 * (PricingActivityChecker); the lines of a cart, which hold their composition, are always priced
 * by it.
 */
final readonly class BundleEffectivePriceSource
{
    public function __construct(
        private PricingActivityChecker $activityChecker,
        private BundlePriceCalculator $bundlePriceCalculator,
    ) {
    }

    /**
     * Of the default composition: what a page lists.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, EffectivePrice> keyed by sale element id; only the sale elements of a bundle in computed mode
     */
    public function forSaleElements(array $productSaleElementsIds, Currency $currency): array
    {
        if ([] === $productSaleElementsIds || !$this->activityChecker->hasComputedBundles()) {
            return [];
        }

        $productIdBySaleElements = [];

        foreach (ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsIds, Criteria::IN)
            ->select(['Id', 'ProductId'])
            ->find() as $row) {
            $productIdBySaleElements[(int) $row['Id']] = (int) $row['ProductId'];
        }

        $prices = $this->bundlePriceCalculator->forBundles(array_values(array_unique($productIdBySaleElements)), $currency);
        $effective = [];

        foreach ($productIdBySaleElements as $saleElementsId => $productId) {
            if (isset($prices[$productId])) {
                $effective[$saleElementsId] = self::effectivePriceOf($saleElementsId, $prices[$productId]);
            }
        }

        return $effective;
    }

    /**
     * Of the composition a cart line holds.
     *
     * A composition the bundle can no longer hold (an item taken out of it, a component whose sale
     * element is gone) has no price: its line is answered by null, and never by an exception, so
     * that showing a cart or changing its currency cannot fail on it. What becomes of such a line
     * is the caller's: the order refuses it.
     *
     * @param array<int|string, array{0: int, 1: int, 2: BundleComposition}> $lines product id, sale element id and composition, by a key of the caller's
     *
     * @return array<int|string, EffectivePrice|null> by the same keys; a line of a bundle that is not in computed mode is absent, one that cannot be priced is null
     */
    public function forLines(array $lines, Currency $currency): array
    {
        // A line that holds a composition is a bundle line whatever the state of the catalog says:
        // that state is a shortcut for the catalog reads, and may lag behind the first bundle written.
        if ([] === $lines) {
            return [];
        }

        $requests = array_map(static fn (array $line): array => [$line[0], $line[2]], $lines);

        try {
            $prices = $this->bundlePriceCalculator->forCompositions($requests, $currency);
        } catch (InvalidBundleCompositionException) {
            // One composition spoils the batch: each is asked on its own, the others keep their price.
            $prices = [];

            foreach ($requests as $key => $request) {
                try {
                    $prices += $this->bundlePriceCalculator->forCompositions([$key => $request], $currency);
                } catch (InvalidBundleCompositionException) {
                    $prices[$key] = null;
                }
            }
        }

        $effective = [];

        foreach ($prices as $key => $price) {
            $effective[$key] = null === $price ? null : self::effectivePriceOf($lines[$key][1], $price);
        }

        return $effective;
    }

    private static function effectivePriceOf(int $productSaleElementsId, BundlePrice $price): EffectivePrice
    {
        return new EffectivePrice(
            $productSaleElementsId,
            (float) $price->untaxedPrice,
            $price->hasDiscount(),
            EffectivePrice::SOURCE_BUNDLE,
            untaxedPrice: $price->untaxedFullPrice,
        );
    }
}
