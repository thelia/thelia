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

use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;

/**
 * What the visitor being served may still buy of a sale element, for what a page or an API read shows.
 *
 * The answer of {@see SellableStock} for the stock the visitor buys from (the own stock of their sale
 * unit when it keeps one) and, when the shop reserves stock, for their cart: what that cart holds
 * itself is not held against it ({@see VisitorCart}, found without opening a session). Whether the shop
 * checks the stock and whether the product is virtual is judged here too, so that every reader shows the
 * same number: null is "no limit".
 */
final readonly class VisitorSellableStock
{
    public function __construct(
        private SellableStock $sellableStock,
        private VisitorCart $visitorCart,
        private SaleUnitContext $saleUnitContext,
    ) {
    }

    /**
     * @return float|null the units that may be bought; null when there is no limit (the sale mode
     *                    sells without limit, the shop does not check the stock, the product is virtual)
     */
    public function forPse(ProductSaleElements $productSaleElements): ?float
    {
        return $this->answers([$productSaleElements], false)[(int) $productSaleElements->getId()]['available'] ?? null;
    }

    /**
     * The same answer for several sale elements, with one read of the reservations whatever their
     * number; the products come from the caller when it has them, otherwise from the sale elements
     * (once per product).
     *
     * `preorder` tells whether the next unit bought would be sold below zero stock: nothing physical is
     * left for it once the other carts have taken theirs, yet it may be bought (a limit not used up, a
     * sale mode that sells without limit): the {@see PreorderRule}.
     *
     * A sale element of a bundle is left out unless asked otherwise: a bundle is supplied by its
     * components, and the callers that tell it do so themselves.
     *
     * @param iterable<ProductSaleElements> $productSaleElements
     * @param array<int, Product>           $products            the products the caller holds, by id
     *
     * @return array<int, array{available: float|null, preorder: bool}> by sale element id
     */
    public function forMany(iterable $productSaleElements, bool $withoutBundles = true, array $products = []): array
    {
        return $this->answers($productSaleElements, $withoutBundles, $products);
    }

    /**
     * @param iterable<ProductSaleElements> $productSaleElements
     * @param array<int, Product>           $products
     *
     * @return array<int, array{available: float|null, preorder: bool}>
     */
    private function answers(iterable $productSaleElements, bool $withoutBundles, array $products = []): array
    {
        $byId = [];

        foreach ($productSaleElements as $one) {
            $byId[(int) $one->getId()] = $one;
        }

        if ([] === $byId) {
            return [];
        }

        $unlimited = ['available' => null, 'preorder' => false];

        if (!ConfigQuery::checkAvailableStock()) {
            return array_map(static fn (): array => $unlimited, $byId);
        }

        $kinds = [];
        $result = [];
        $counted = [];

        foreach ($byId as $id => $one) {
            $productId = (int) $one->getProductId();

            if (!isset($kinds[$productId])) {
                $product = $products[$productId] ?? $one->getProduct();
                $kinds[$productId] = ['virtual' => 1 === (int) $product->getVirtual(), 'bundle' => 1 === (int) $product->getIsBundle()];
            }

            $kind = $kinds[$productId];

            if ($withoutBundles && $kind['bundle']) {
                continue;
            }

            if ($kind['virtual']) {
                $result[$id] = $unlimited;

                continue;
            }

            $counted[$id] = $one;
        }

        // The reservations are counted on the stock the physical quantity is read from: the own stock
        // of the sale unit being served, even for a visitor who has no cart yet.
        $cart = $this->visitorCart->current();
        $saleUnitId = $cart?->getSaleUnitId() ?? $this->saleUnitContext->getSaleUnit()?->getId();

        foreach ($this->sellableStock->answersFor($counted, $saleUnitId, $this->visitorCart->currentId()) as $id => $answer) {
            $result[$id] = ['available' => $answer->available, 'preorder' => $answer->nextUnitIsPreorder()];
        }

        return $result;
    }
}
