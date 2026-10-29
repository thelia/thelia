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

namespace Thelia\Domain\Cart\Bundle;

use Thelia\Domain\Catalog\Bundle\BundleAvailability;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\SaleUnit\Stock\SaleUnitAvailableStock;
use Thelia\Model\ProductSaleElements;

/**
 * How many units of a cart line the shop can supply: the one place that answers it, for
 * the form, the API, the cart and the ordering by reference alike.
 *
 * A line of a bundle is supplied by its components, never by the stock of the sale element
 * that stands for the bundle (which stays at zero): the answer is the number of bundles the
 * retained components allow. Any other line is supplied by the stock of the sale unit the
 * visitor buys from when it keeps one, the national counter otherwise; a virtual product
 * has no stock to run out of.
 *
 * Whether the shop refuses beyond it is the setting of the shop (ConfigQuery::checkAvailableStock()),
 * which the caller reads. What a list shows of a bundle before any composition is chosen is
 * BundleDisplayedStock's.
 */
final readonly class CartLineAvailability
{
    /** What a line nothing limits is supplied up to: the one value of BundleAvailability, compared as a float. */
    public const UNLIMITED = BundleAvailability::UNLIMITED;

    public function __construct(
        private BundleAvailability $bundleAvailability,
        private ?SaleUnitAvailableStock $saleUnitAvailableStock = null,
    ) {
    }

    /**
     * @param BundleComposition|null $composition the composition of the line, null for a line that is not a bundle
     */
    public function forLine(ProductSaleElements $productSaleElements, ?BundleComposition $composition = null): float
    {
        if (null !== $composition) {
            return (float) $this->bundleAvailability->forComposition((int) $productSaleElements->getProductId(), $composition);
        }

        if (0 !== (int) $productSaleElements->getProduct()->getVirtual()) {
            return (float) self::UNLIMITED;
        }

        return $this->saleUnitAvailableStock?->availableQuantity($productSaleElements) ?? (float) $productSaleElements->getQuantity();
    }

    /**
     * Of the bundle lines of a cart, in one pass: a composition of a bundle is asked once however
     * many lines hold it, and a cart without bundle line asks nothing.
     *
     * @param array<int|string, array{0: ProductSaleElements, 1: BundleComposition}> $lines the sale element and the composition of each line, by a key of the caller's
     *
     * @return array<int|string, float> by the same keys
     */
    public function forBundleLines(array $lines): array
    {
        $requests = [];
        $answerOf = [];

        foreach ($lines as $key => [$productSaleElements, $composition]) {
            $answer = (int) $productSaleElements->getProductId().'|'.$composition->toCanonicalJson();
            $requests[$answer] ??= [(int) $productSaleElements->getProductId(), $composition];
            $answerOf[$key] = $answer;
        }

        $asked = $this->bundleAvailability->forCompositions($requests);
        $available = [];

        foreach ($answerOf as $key => $answer) {
            $available[$key] = (float) $asked[$answer];
        }

        return $available;
    }
}
