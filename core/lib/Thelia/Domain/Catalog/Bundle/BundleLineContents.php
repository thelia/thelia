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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * What the components a customer retained for a bundle line weigh and are made of, read for
 * all the lines of a cart at once: what the weight and the virtual status of a cart need,
 * from a model that has no container to ask a service from.
 *
 * A line with no composition (a bundle a promotion offers) is read with the default
 * composition of its bundle, as it is ordered.
 */
#[Exclude]
final class BundleLineContents
{
    /**
     * @param array<int|string, array{0: int, 1: BundleComposition|null}> $lines bundle product id and composition (null for the default one), by a key of the caller's
     *
     * @return array<int|string, array{weight: float, virtual: bool, components: list<array{bundleItemId: int, productSaleElementsId: int, productId: int, quantity: string}>}>
     *                                                                                                                                                                          by the same keys: the weight of one bundle (sum of the weight of the retained components times
     *                                                                                                                                                                          their quantity), whether every retained component is a virtual product, and the retained
     *                                                                                                                                                                          components in the order of the bundle; an orphaned item (its sale element deleted) is left out
     */
    public static function of(array $lines): array
    {
        if ([] === $lines) {
            return [];
        }

        $itemsByBundle = (new BundleItemRepository())->forBundles(array_values(array_unique(array_map(static fn (array $line): int => $line[0], $lines))));

        $saleElementsIds = [];

        foreach ($itemsByBundle as $items) {
            foreach ($items as $item) {
                if (null !== $item->getProductSaleElementsId()) {
                    $saleElementsIds[] = (int) $item->getProductSaleElementsId();
                }
            }
        }

        $saleElements = [];

        if ([] !== $saleElementsIds) {
            foreach (ProductSaleElementsQuery::create()
                ->filterById(array_values(array_unique($saleElementsIds)), Criteria::IN)
                ->joinProduct()
                ->withColumn('Product.Virtual', 'ProductVirtual')
                ->select(['Id', 'ProductId', 'Weight', 'ProductVirtual'])
                ->find() as $row) {
                $saleElements[(int) $row['Id']] = $row;
            }
        }

        $contents = [];

        foreach ($lines as $key => [$bundleProductId, $composition]) {
            $composition ??= BundleCompositionResolver::defaultComposition($itemsByBundle[$bundleProductId] ?? []);
            $weight = 0.0;
            $virtual = true;
            $components = [];

            foreach ($itemsByBundle[$bundleProductId] ?? [] as $item) {
                $quantity = $composition->quantityOf((int) $item->getId());
                $saleElementsId = $item->getProductSaleElementsId();

                if ('0' === $quantity || null === $saleElementsId || !isset($saleElements[$saleElementsId])) {
                    continue;
                }

                $row = $saleElements[$saleElementsId];
                $weight += (float) $row['Weight'] * BundleDecimal::toMicro($quantity) / BundleDecimal::SCALE;
                $virtual = $virtual && (bool) $row['ProductVirtual'];
                $components[] = [
                    'bundleItemId' => (int) $item->getId(),
                    'productSaleElementsId' => $saleElementsId,
                    'productId' => (int) $row['ProductId'],
                    'quantity' => $quantity,
                ];
            }

            $contents[$key] = ['weight' => $weight, 'virtual' => [] !== $components && $virtual, 'components' => $components];
        }

        return $contents;
    }
}
