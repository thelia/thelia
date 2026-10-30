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

namespace Thelia\Domain\Catalog\Bundle;

use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Model\ProductBundleItem;

/**
 * Turns what a caller proposes for a bundle into the composition the bundle will hold, or
 * refuses it. The caller says which components and how many; what they cost, and whether
 * they exist, is read from the catalogue here and nowhere else.
 *
 * The bounds of an item: a quantity is allowed between its minimum and its maximum, each of
 * them its fixed quantity when not set. A required item is always in, so its quantity is at
 * least one bound above zero; an optional item may also be left out (quantity zero).
 *
 * The default composition holds every required item at its quantity, and every optional item
 * at its minimum when that is above zero, out otherwise; an optional item that cannot be sold
 * is out of it even then, so that one offline housse does not make the bundle unsellable.
 *
 * A bundle with nothing in its composition has nothing to sell and is refused, whatever the
 * shop does with its stock. A component the visitor may not see (a private drop, a rule of a
 * module: ProductVisibility) cannot be bought through a public bundle either: it makes a
 * bundle that requires it unsellable for that visitor.
 */
final readonly class BundleCompositionResolver
{
    public function __construct(
        private BundleItemRepository $bundleItemRepository,
        private BundleComponentReader $bundleComponentReader,
    ) {
    }

    /**
     * @param BundleComposition|null $proposed null for the default composition
     *
     * @throws InvalidBundleCompositionException the composition cannot be held by the bundle
     */
    public function resolve(int $bundleProductId, ?BundleComposition $proposed = null): BundleComposition
    {
        if ([] === $this->bundleItemRepository->bundleProductIds([$bundleProductId])) {
            throw InvalidBundleCompositionException::notABundle($bundleProductId);
        }

        $items = $this->bundleItemRepository->forBundles([$bundleProductId])[$bundleProductId] ?? [];
        $saleElementsIds = array_map(static fn (ProductBundleItem $item): ?int => $item->getProductSaleElementsId(), $items);
        $components = $this->bundleComponentReader->read($saleElementsIds);
        $hidden = array_fill_keys($this->bundleComponentReader->hiddenFromVisitor($saleElementsIds), true);

        if (null === $proposed) {
            $effective = self::defaultComposition($items, $components, $hidden);
        } else {
            $effective = self::validate($bundleProductId, $items, $proposed);
        }

        if ($effective->isEmpty()) {
            throw InvalidBundleCompositionException::bundleHoldsNothing($bundleProductId);
        }

        $this->assertSellable($items, $effective, $components, $hidden);

        return $effective;
    }

    /**
     * Without any read of the catalogue: the items alone settle it. An optional item at a
     * minimum above zero is in, unless the caller, which has read the catalogue, hands over
     * the components and this one cannot be sold (or the visitor may not see it).
     *
     * @param list<ProductBundleItem>          $items
     * @param array<int, BundleComponent>|null $components the components the items name, by sale element id; null when the caller has not read them
     * @param array<int, true>                 $hidden     the sale elements the visitor may not see, by id
     */
    public static function defaultComposition(array $items, ?array $components = null, array $hidden = []): BundleComposition
    {
        $lines = [];

        foreach ($items as $item) {
            $micro = $item->getRequired()
                ? BundleDecimal::toMicro((string) $item->getQuantity())
                : (null === $item->getMinQuantity() ? 0 : BundleDecimal::toMicro((string) $item->getMinQuantity()));

            $saleElementsId = (int) $item->getProductSaleElementsId();

            if (!$item->getRequired() && null !== $components && (!($components[$saleElementsId]->sellable ?? false) || isset($hidden[$saleElementsId]))) {
                $micro = 0;
            }

            $lines[] = ['bundleItemId' => (int) $item->getId(), 'quantity' => BundleDecimal::fromMicro($micro)];
        }

        return BundleComposition::fromArray($lines);
    }

    /**
     * @param list<ProductBundleItem> $items
     */
    private static function validate(int $bundleProductId, array $items, BundleComposition $proposed): BundleComposition
    {
        $itemsById = [];

        foreach ($items as $item) {
            $itemsById[(int) $item->getId()] = $item;
        }

        foreach ($proposed->lines as $line) {
            if (!isset($itemsById[$line->bundleItemId])) {
                throw InvalidBundleCompositionException::unknownItem($line->bundleItemId, $bundleProductId);
            }
        }

        $lines = [];

        foreach ($itemsById as $itemId => $item) {
            $chosen = BundleDecimal::toMicro($proposed->quantityOf($itemId));
            $lowest = BundleDecimal::toMicro((string) ($item->getMinQuantity() ?? $item->getQuantity()));
            $highest = BundleDecimal::toMicro((string) ($item->getMaxQuantity() ?? $item->getQuantity()));

            if (0 === $chosen) {
                if ($item->getRequired()) {
                    throw InvalidBundleCompositionException::requiredItemMissing($itemId);
                }

                continue;
            }

            if ($chosen < $lowest || $chosen > $highest) {
                throw InvalidBundleCompositionException::quantityOutOfBounds($itemId, BundleDecimal::fromMicro($chosen), BundleDecimal::fromMicro($lowest), BundleDecimal::fromMicro($highest));
            }

            $lines[] = ['bundleItemId' => $itemId, 'quantity' => BundleDecimal::fromMicro($chosen)];
        }

        return BundleComposition::fromArray($lines);
    }

    /**
     * @param list<ProductBundleItem>     $items
     * @param array<int, BundleComponent> $components
     * @param array<int, true>            $hidden
     */
    private function assertSellable(array $items, BundleComposition $effective, array $components, array $hidden): void
    {
        foreach ($items as $item) {
            if ('0' === $effective->quantityOf((int) $item->getId())) {
                continue;
            }

            $saleElementsId = $item->getProductSaleElementsId();

            if (null === $saleElementsId || !($components[$saleElementsId]->sellable ?? false) || isset($hidden[$saleElementsId])) {
                throw InvalidBundleCompositionException::componentNotSellable((int) $item->getId());
            }
        }
    }
}
