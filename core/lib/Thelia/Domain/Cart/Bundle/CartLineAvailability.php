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
use Thelia\Domain\Catalog\Stock\SellableStock;
use Thelia\Domain\SaleUnit\Stock\SaleUnitAvailableStock;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
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
 * A shop that does not check its stock (ConfigQuery::checkAvailableStock()) supplies every line
 * without limit, the line of a bundle as any other: nothing is read. What a list shows of a bundle before any composition is chosen is
 * BundleDisplayedStock's.
 */
final readonly class CartLineAvailability
{
    /** What a line nothing limits is supplied up to: the one value of BundleAvailability, compared as a float. */
    public const UNLIMITED = BundleAvailability::UNLIMITED;

    public function __construct(
        private BundleAvailability $bundleAvailability,
        private ?SaleUnitAvailableStock $saleUnitAvailableStock = null,
        private ?SellableStock $sellableStock = null,
    ) {
    }

    /**
     * What the shop can supply of a line: for a sale element, what it may still sell (SellableStock:
     * the stock, what its sale mode allows below zero, less what other carts reserve), unlimited when
     * its sale mode sells without limit.
     *
     * @param BundleComposition|null $composition the composition of the line, null for a line that is not a bundle
     * @param Cart|null              $cart        the cart asking, whose own reservation is not held against it
     */
    public function forLine(ProductSaleElements $productSaleElements, ?BundleComposition $composition = null, ?Cart $cart = null): float
    {
        if (!ConfigQuery::checkAvailableStock()) {
            return (float) self::UNLIMITED;
        }

        if (null !== $composition) {
            return (float) $this->bundleAvailability->forComposition((int) $productSaleElements->getProductId(), $composition);
        }

        if (0 !== (int) $productSaleElements->getProduct()->getVirtual()) {
            return (float) self::UNLIMITED;
        }

        $cartId = (int) $cart?->getId();

        return ($this->sellableStock ?? SellableStock::withoutReservations())->forPse(
            $productSaleElements,
            $this->saleUnitAvailableStock?->availableQuantity($productSaleElements),
            $cart?->getSaleUnitId(),
            $cartId > 0 ? $cartId : null,
        ) ?? (float) self::UNLIMITED;
    }

    /**
     * What the carts other than this one hold of the stock of a sale element, on the stock the cart
     * sells from; 0.0 when the shop does not reserve.
     */
    public function reservedByOtherCarts(ProductSaleElements $productSaleElements, Cart $cart): float
    {
        $cartId = (int) $cart->getId();

        return ($this->sellableStock ?? SellableStock::withoutReservations())->reservedByOtherCarts(
            (int) $productSaleElements->getId(),
            null === $cart->getSaleUnitId() ? null : (int) $cart->getSaleUnitId(),
            $cartId > 0 ? $cartId : null,
        );
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
        if (!ConfigQuery::checkAvailableStock()) {
            return array_map(static fn (): float => (float) self::UNLIMITED, $lines);
        }

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
