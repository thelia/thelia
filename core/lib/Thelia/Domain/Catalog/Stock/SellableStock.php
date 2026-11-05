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

namespace Thelia\Domain\Catalog\Stock;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\SaleUnit\Stock\SaleUnitAvailableStock;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * How many units of a sale element a cart may still buy: the one place that decides it.
 *
 * It is the physical stock (the national counter, or the own stock of a sale unit when the
 * caller reads that one), plus what the sale element sells below zero (its out-of-stock
 * policy), less what the carts other than the asking one keep reserved. Every reader of
 * availability goes through here: the cart, the checkout, the API, the loops, the facades.
 *
 * Whether the shop checks the stock at all, and whether a product is virtual, is decided by
 * the caller before it asks: this class answers for a product whose stock counts.
 */
final readonly class SellableStock
{
    public function __construct(
        private ReservedQuantityProvider $reservedQuantityProvider,
        private ?SaleUnitAvailableStock $saleUnitAvailableStock = null,
    ) {
    }

    /** For the readers that cannot be injected (a model): the same arithmetic, with no reservation. */
    public static function withoutReservations(): self
    {
        return new self(new NoReservedQuantity());
    }

    /**
     * The physical stock and what the policy allows below zero, before any reservation. Null
     * when the policy sells without limit.
     */
    public static function ceilingOf(float $physicalQuantity, OutOfStockPolicy $policy, ?float $preorderLimit): ?float
    {
        return match ($policy) {
            OutOfStockPolicy::Refuse => $physicalQuantity,
            OutOfStockPolicy::Unlimited => null,
            OutOfStockPolicy::Limited => $physicalQuantity + max(0.0, $preorderLimit ?? 0.0),
        };
    }

    /**
     * What a sale element may still sell, without reservations. Null means without limit.
     */
    public static function ceilingOfPse(ProductSaleElements $productSaleElements, ?float $physicalQuantity = null): ?float
    {
        return self::ceilingOf(
            $physicalQuantity ?? (float) $productSaleElements->getQuantity(),
            OutOfStockPolicy::fromStored($productSaleElements->getOutOfStockPolicy()),
            $productSaleElements->getPreorderLimit(),
        );
    }

    /**
     * What the carts other than the one asking hold of the stock of a sale element; 0.0, read from
     * nothing, when the shop does not reserve.
     */
    public function reservedByOtherCarts(int $productSaleElementsId, ?int $saleUnitId, ?int $excludedCartId): float
    {
        return $this->reservedQuantityProvider->reservedByOtherCarts($productSaleElementsId, $saleUnitId, $excludedCartId);
    }

    /**
     * @param float|null $physicalQuantity the stock the caller sells from when it is not the national counter of the
     *                                     sale element (the own stock of a sale unit, the shared counter otherwise)
     * @param int|null   $saleUnitId       the sale unit the reservations are counted on, for a cart that has one
     * @param int|null   $excludedCartId   the cart asking, whose own reservation is not held against it
     *
     * @return float|null the units that may be bought, never below zero; null when there is no limit
     */
    public function forPse(ProductSaleElements $productSaleElements, ?float $physicalQuantity = null, ?int $saleUnitId = null, ?int $excludedCartId = null): ?float
    {
        $ceiling = self::ceilingOfPse($productSaleElements, $physicalQuantity);

        if (null === $ceiling) {
            return null;
        }

        $reserved = $this->reservedQuantityProvider->reservedByOtherCarts((int) $productSaleElements->getId(), $saleUnitId, $excludedCartId);

        return max(0.0, $ceiling - $reserved);
    }

    /**
     * {@see forPse()} for a batch of sale elements: one read of the reservations whatever their number
     * (none for an empty batch).
     *
     * The stock each is sold from is the own stock of the sale unit of the request when it keeps one,
     * the national counter otherwise (one read for the lot, none without a sale unit).
     *
     * @param iterable<ProductSaleElements> $productSaleElements
     *
     * @return array<int, float|null> by sale element id; null when there is no limit
     */
    public function forMany(iterable $productSaleElements, ?int $saleUnitId = null, ?int $excludedCartId = null): array
    {
        return array_map(
            static fn (SellableQuantity $answer): ?float => $answer->available,
            $this->answersFor($productSaleElements, $saleUnitId, $excludedCartId),
        );
    }

    /**
     * {@see forMany()} with the figures each answer comes from.
     *
     * @param iterable<ProductSaleElements> $productSaleElements
     *
     * @return array<int, SellableQuantity> by sale element id
     */
    public function answersFor(iterable $productSaleElements, ?int $saleUnitId = null, ?int $excludedCartId = null): array
    {
        $byId = [];

        foreach ($productSaleElements as $one) {
            $byId[(int) $one->getId()] = $one;
        }

        if ([] === $byId) {
            return [];
        }

        $ownQuantities = $this->saleUnitAvailableStock?->ownQuantities(array_keys($byId)) ?? [];
        $ceilings = [];
        $physicals = [];

        foreach ($byId as $id => $one) {
            $physicals[$id] = $ownQuantities[$id] ?? (float) $one->getQuantity();
            $ceilings[$id] = self::ceilingOfPse($one, $physicals[$id]);
        }

        // Every sale element is asked, the one that sells without limit included: what the other carts hold
        // of it says whether the next unit is a preorder, though it never limits what may be bought.
        $reserved = $this->reservedQuantityProvider->reservedByOtherCartsOf(array_keys($byId), $saleUnitId, $excludedCartId);
        $answers = [];

        foreach ($byId as $id => $one) {
            $held = $reserved[$id] ?? 0.0;
            $ceiling = $ceilings[$id];

            $answers[$id] = new SellableQuantity(
                null === $ceiling ? null : max(0.0, $ceiling - $held),
                $physicals[$id],
                $held,
                OutOfStockPolicy::fromStored($one->getOutOfStockPolicy()),
            );
        }

        return $answers;
    }

    /**
     * What each line of a cart may still be, for the whole cart in one go: one read of the
     * reservations, one of the stock of the sale unit, one of the sale elements.
     *
     * A line is judged against the other carts only: what the cart holds itself is never held against
     * it. `preorder` is the verdict of {@see PreorderRule::isPreorder()} on the quantity of the line.
     * Left out of the answer: the lines of a bundle (supplied by their components, see
     * CartLineAvailability), and every line when the shop does not check the stock. A virtual product
     * has no limit and no preorder.
     *
     * @param array<int, ProductSaleElements> $saleElementsAlreadyRead the sale elements of the lines the caller read already,
     *                                                                 by id, with their product: they are not read again
     *
     * @return array<int, array{available: float|null, preorder: bool}> by cart line id; available is null when there is no limit
     */
    public function forCart(Cart $cart, array $saleElementsAlreadyRead = []): array
    {
        if (!ConfigQuery::checkAvailableStock()) {
            return [];
        }

        $lines = [];

        /** @var CartItem $line */
        foreach ($cart->getCartItems() as $line) {
            if (null === $line->getBundleComposition()) {
                $lines[(int) $line->getId()] = $line;
            }
        }

        if ([] === $lines) {
            return [];
        }

        $saleElements = [];
        $missing = [];

        foreach ($lines as $line) {
            $saleElementId = (int) $line->getProductSaleElementsId();

            if (isset($saleElementsAlreadyRead[$saleElementId])) {
                $saleElements[$saleElementId] = $saleElementsAlreadyRead[$saleElementId];
            } else {
                $missing[$saleElementId] = $saleElementId;
            }
        }

        if ([] !== $missing) {
            /** @var ProductSaleElements $saleElement */
            foreach (ProductSaleElementsQuery::create()
                ->filterById(array_values($missing), Criteria::IN)
                ->joinWithProduct()
                ->find() as $saleElement) {
                $saleElements[(int) $saleElement->getId()] = $saleElement;
            }
        }

        $cartId = (int) $cart->getId();
        $answers = $this->answersFor(
            array_filter($saleElements, static fn (ProductSaleElements $saleElement): bool => 1 !== (int) $saleElement->getProduct()->getVirtual() && 1 !== (int) $saleElement->getProduct()->getIsBundle()),
            $cart->getSaleUnitId(),
            $cartId > 0 ? $cartId : null,
        );
        $result = [];

        foreach ($lines as $lineId => $line) {
            $saleElement = $saleElements[(int) $line->getProductSaleElementsId()] ?? null;

            if (null === $saleElement || 1 === (int) $saleElement->getProduct()->getIsBundle()) {
                continue;
            }

            $answer = $answers[(int) $saleElement->getId()] ?? null;

            $result[$lineId] = null === $answer
                ? ['available' => null, 'preorder' => false]
                : ['available' => $answer->available, 'preorder' => $answer->isPreorderFor((float) $line->getQuantity())];
        }

        return $result;
    }
}
