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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\BundleCompositionResolver;
use Thelia\Domain\Catalog\Bundle\BundleDecimal;
use Thelia\Domain\Catalog\Bundle\BundleItemRepository;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Domain\Order\Exception\BundleLineNotOrderableException;
use Thelia\Model\CartItem;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * What the pack lines of a cart are made of, read once for the whole cart: the components
 * the order takes from the stock, with the quantity of each for the quantity of the pack
 * bought.
 *
 * The composition of a cart line says which items of the pack the buyer kept and how many
 * of each. It was valid when the line was added, and the catalogue may have moved since: a
 * component deleted or taken offline, an item taken out of the pack, a required item added,
 * bounds narrowed. Each one is therefore judged again here by the one judge of a
 * composition, BundleCompositionResolver, and a line it refuses refuses the order, naming
 * the pack, before anything is written or taken from the stock.
 */
final readonly class BundleOrderPlanner
{
    public function __construct(
        private BundleItemRepository $bundleItemRepository,
        private BundleCompositionResolver $bundleCompositionResolver,
        private BundleCatalogState $bundleCatalogState,
    ) {
    }

    /**
     * @param iterable<CartItem> $cartItems
     *
     * @return array<int, list<BundleOrderComponent>> keyed by the id of the cart line, for the pack lines only
     *                                                (a pack line with no component is present, with an empty list);
     *                                                a line of a bundle product with no composition, the one a
     *                                                promotion offers, is read with the default composition
     *
     * @throws BundleLineNotOrderableException a pack line whose composition the catalogue no longer holds
     */
    public function componentsOf(iterable $cartItems): array
    {
        $compositions = [];
        $bundleProductIds = [];

        foreach ($cartItems as $cartItem) {
            try {
                $composition = $cartItem->getBundleCompositionValue();
            } catch (InvalidBundleCompositionException $malformed) {
                throw self::refusal($cartItem, $malformed);
            }

            // The line a promotion offers is placed with no composition to choose from, and a
            // bundle is never ordered as a product: it takes the composition it has by default.
            // Any other line of a bundle holds its composition (CartItem refuses it otherwise), so
            // only an offered line asks for its product: a cart without a bundle asks nothing.
            $isOfferedBundle = null === $composition
                && 1 === (int) $cartItem->getIsOffered()
                && 1 === (int) $cartItem->getProduct()->getIsBundle();

            if (null === $composition && !$isOfferedBundle) {
                continue;
            }

            $bundleProductId = (int) $cartItem->getProductId();

            try {
                $composition = $this->bundleCompositionResolver->resolve($bundleProductId, $composition);
            } catch (InvalidBundleCompositionException $invalid) {
                throw self::refusal($cartItem, $invalid);
            }

            $compositions[(int) $cartItem->getId()] = [$cartItem, $composition];
            $bundleProductIds[] = $bundleProductId;
        }

        if ([] === $compositions) {
            return [];
        }

        $itemsByBundle = $this->bundleItemRepository->forBundles($bundleProductIds);
        $retainedByCartItem = [];
        $saleElementsIds = [];

        foreach ($compositions as $cartItemId => [$cartItem, $composition]) {
            $retainedByCartItem[$cartItemId] = [];

            foreach ($itemsByBundle[(int) $cartItem->getProductId()] ?? [] as $item) {
                $quantity = $composition->quantityOf((int) $item->getId());

                if ('0' === $quantity) {
                    continue;
                }

                // Read again a moment after the resolver judged it: an item orphaned in
                // between is refused like any other.
                if (null === $item->getProductSaleElementsId()) {
                    throw self::refusal($cartItem, InvalidBundleCompositionException::componentNotSellable((int) $item->getId()));
                }

                $saleElementsIds[] = (int) $item->getProductSaleElementsId();
                $retainedByCartItem[$cartItemId][] = [$item, $quantity];
            }
        }

        $saleElements = [];

        if ([] !== $saleElementsIds) {
            foreach (ProductSaleElementsQuery::create()
                ->filterById(array_values(array_unique($saleElementsIds)), Criteria::IN)
                ->joinWithProduct()
                ->find() as $productSaleElements) {
                $saleElements[(int) $productSaleElements->getId()] = $productSaleElements;
            }
        }

        $plan = [];

        foreach ($retainedByCartItem as $cartItemId => $retained) {
            $cartItem = $compositions[$cartItemId][0];
            $packQuantityMicro = BundleDecimal::toMicro((float) $cartItem->getQuantity());
            $plan[$cartItemId] = [];

            foreach ($retained as [$item, $quantity]) {
                $productSaleElements = $saleElements[(int) $item->getProductSaleElementsId()]
                    ?? throw self::refusal($cartItem, InvalidBundleCompositionException::componentNotSellable((int) $item->getId()));

                $plan[$cartItemId][] = new BundleOrderComponent(
                    $productSaleElements->getProduct(),
                    $productSaleElements,
                    BundleDecimal::multiply(BundleDecimal::toMicro($quantity), $packQuantityMicro) / BundleDecimal::SCALE,
                );
            }
        }

        return $plan;
    }

    /**
     * A line of a bundle product that is neither offered nor holding a composition is no pack
     * the order can take from the stock: placed as a product it would be charged at the price of
     * the technical sale element and leave the stock of its components untouched. CartItem
     * refuses to write such a line, and this is the judge of one that got there anyway (the
     * product turned into a bundle after the line was added).
     *
     * Read once for the whole cart, for the cart step: a shop without a bundle asks nothing,
     * and neither does a cart whose lines all hold a composition or are offered. The order
     * itself asks it of each line with the product it loads anyway.
     *
     * @param iterable<CartItem> $cartItems
     *
     * @throws BundleLineNotOrderableException
     */
    public function assertNoBundleLineWithoutComposition(iterable $cartItems): void
    {
        if (!$this->bundleCatalogState->hasBundles()) {
            return;
        }

        $candidates = [];

        foreach ($cartItems as $cartItem) {
            if (null === $cartItem->getBundleComposition() && 1 !== (int) $cartItem->getIsOffered()) {
                $candidates[(int) $cartItem->getProductId()][] = $cartItem;
            }
        }

        if ([] === $candidates) {
            return;
        }

        $bundleProductIds = ProductQuery::create()
            ->filterById(array_keys($candidates), Criteria::IN)
            ->filterByIsBundle(1)
            ->select('Id')
            ->find()
            ->toArray();

        foreach ($bundleProductIds as $bundleProductId) {
            throw self::withoutComposition($candidates[(int) $bundleProductId][0]);
        }
    }

    /**
     * The same judgement for a line whose product is already loaded.
     *
     * @throws BundleLineNotOrderableException
     */
    public static function assertNotABundleLineWithoutComposition(CartItem $cartItem, Product $product): void
    {
        if (1 === (int) $product->getIsBundle() && 1 !== (int) $cartItem->getIsOffered() && null === $cartItem->getBundleComposition()) {
            throw self::withoutComposition($cartItem);
        }
    }

    private static function withoutComposition(CartItem $cartItem): BundleLineNotOrderableException
    {
        return self::refusal($cartItem, InvalidBundleCompositionException::malformed(\sprintf('product %d is a bundle and its cart line holds no composition', (int) $cartItem->getProductId())));
    }

    private static function refusal(CartItem $cartItem, InvalidBundleCompositionException $reason): BundleLineNotOrderableException
    {
        return new BundleLineNotOrderableException((string) $cartItem->getProduct()->getRef(), (int) $cartItem->getId(), $reason);
    }
}
