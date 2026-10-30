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
use Thelia\Domain\Catalog\Bundle\BundleComposition;
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
        $cartItems = \is_array($cartItems) ? $cartItems : iterator_to_array($cartItems, false);
        [$refusals, $compositions] = $this->judge($cartItems);

        foreach ($cartItems as $cartItem) {
            if (isset($refusals[(int) $cartItem->getId()])) {
                throw $refusals[(int) $cartItem->getId()];
            }
        }

        if ([] === $compositions) {
            return [];
        }

        $itemsByBundle = $this->bundleItemRepository->forBundles(array_values(array_unique(array_map(
            static fn (array $line): int => (int) $line[0]->getProductId(),
            $compositions,
        ))));
        $retainedByCartItem = [];
        $saleElementsIds = [];

        foreach ($compositions as $cartItemId => [$cartItem, $composition]) {
            $retainedByCartItem[$cartItemId] = [];

            foreach ($itemsByBundle[(int) $cartItem->getProductId()] ?? [] as $item) {
                $quantity = $composition->quantityOf((int) $item->getId());

                if ('0' === $quantity) {
                    continue;
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
     * The verdict of the order on every pack line of the cart at once, for a page that has to say which
     * line the order will refuse: the pack lines the catalogue no longer holds, by the id of the cart line,
     * with the refusal the order would answer. Empty when every pack can be ordered.
     *
     * The same judge as componentsOf() (judge()), plus assertNoBundleLineWithoutComposition(), the compositions
     * resolved in one pass for the whole cart (BundleCompositionResolver::resolveMany) instead of
     * stopping at the first refusal. A cart with no pack line asks the catalogue nothing.
     *
     * @param iterable<CartItem> $cartItems
     *
     * @return array<int, BundleLineNotOrderableException>
     */
    public function refusalsOf(iterable $cartItems): array
    {
        $cartItems = \is_array($cartItems) ? $cartItems : iterator_to_array($cartItems, false);
        [$refusals] = $this->judge($cartItems);

        foreach ($this->linesWithoutComposition($cartItems) as $cartItem) {
            $refusals[(int) $cartItem->getId()] ??= self::withoutComposition($cartItem);
        }

        ksort($refusals);

        return $refusals;
    }

    /**
     * The one verdict of the order on the compositions of the cart lines: each line that holds one (or is
     * an offered bundle) judged by BundleCompositionResolver in a single pass, the catalogue read once.
     *
     * @param list<CartItem> $cartItems
     *
     * @return array{0: array<int, BundleLineNotOrderableException>, 1: array<int, array{0: CartItem, 1: BundleComposition}>} the refusals and the resolved compositions, by cart line id
     */
    private function judge(array $cartItems): array
    {
        $refusals = [];
        $requests = [];
        $lines = [];

        foreach ($cartItems as $cartItem) {
            $cartItemId = (int) $cartItem->getId();

            try {
                $composition = $cartItem->getBundleCompositionValue();
            } catch (InvalidBundleCompositionException $malformed) {
                $refusals[$cartItemId] = self::refusal($cartItem, $malformed);

                continue;
            }

            // The line a promotion offers is placed with no composition to choose from, and a
            // bundle is never ordered as a product: it takes the composition it has by default.
            // Any other line of a bundle holds its composition (CartItem refuses it otherwise), so
            // only an offered line asks for its product: a cart without a bundle asks nothing.
            if (null === $composition && !$this->isOfferedBundle($cartItem)) {
                continue;
            }

            $requests[$cartItemId] = [(int) $cartItem->getProductId(), $composition];
            $lines[$cartItemId] = $cartItem;
        }

        $compositions = [];

        foreach ($this->bundleCompositionResolver->resolveMany($requests) as $cartItemId => $verdict) {
            if ($verdict instanceof InvalidBundleCompositionException) {
                $refusals[$cartItemId] = self::refusal($lines[$cartItemId], $verdict);
            } else {
                $compositions[$cartItemId] = [$lines[$cartItemId], $verdict];
            }
        }

        return [$refusals, $compositions];
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
        foreach ($this->linesWithoutComposition($cartItems) as $cartItem) {
            throw self::withoutComposition($cartItem);
        }
    }

    /**
     * The lines of a bundle product that are neither offered nor hold a composition, in the order of the
     * cart.
     *
     * @param iterable<CartItem> $cartItems
     *
     * @return list<CartItem>
     */
    private function linesWithoutComposition(iterable $cartItems): array
    {
        if (!$this->bundleCatalogState->hasBundles()) {
            return [];
        }

        $candidates = [];

        foreach ($cartItems as $cartItem) {
            if (null === $cartItem->getBundleComposition() && 1 !== (int) $cartItem->getIsOffered()) {
                $candidates[(int) $cartItem->getProductId()][] = $cartItem;
            }
        }

        if ([] === $candidates) {
            return [];
        }

        $bundleProductIds = array_flip(array_map('intval', ProductQuery::create()
            ->filterById(array_keys($candidates), Criteria::IN)
            ->filterByIsBundle(1)
            ->select('Id')
            ->find()
            ->toArray()));
        $lines = [];

        foreach ($candidates as $productId => $candidateLines) {
            if (isset($bundleProductIds[$productId])) {
                array_push($lines, ...$candidateLines);
            }
        }

        return $lines;
    }

    private function isOfferedBundle(CartItem $cartItem): bool
    {
        return 1 === (int) $cartItem->getIsOffered() && 1 === (int) $cartItem->getProduct()->getIsBundle();
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
