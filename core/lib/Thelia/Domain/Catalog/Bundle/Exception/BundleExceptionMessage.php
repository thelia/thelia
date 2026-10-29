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

namespace Thelia\Domain\Catalog\Bundle\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProductBundleItemQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The words of the refusals of a bundle: translated in the catalogue of the core, and naming a product by the
 * reference printed on the catalogue, never by an id. Read on a refusal only, which a person meets once, so the
 * queries that fetch a reference cost nothing on the paths that succeed.
 */
#[Exclude]
final class BundleExceptionMessage
{
    /**
     * The English sentence where no translator is up yet (a message built before the kernel boots).
     *
     * @param array<string, string> $parameters
     */
    public static function translate(string $key, array $parameters = []): string
    {
        try {
            $translator = Translator::getInstance();
        } catch (\RuntimeException) {
            return strtr($key, $parameters);
        }

        return $translator->trans($key, $parameters);
    }

    /**
     * What names a product in a sentence: its reference, and the id only when no product holds it any more.
     */
    public static function productLabel(int $productId): string
    {
        return self::referenceOfTheProduct($productId) ?? (string) $productId;
    }

    /**
     * What names the product of a sale element in a sentence: the reference of that product, the id of the sale
     * element when it does not exist.
     */
    public static function saleElementsLabel(int $productSaleElementsId): string
    {
        /** @var string|null $reference a single selected column */
        $reference = ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsId)
            ->joinProduct()
            ->withColumn('Product.Ref', 'ProductReference')
            ->select(['ProductReference'])
            ->findOne();

        return null !== $reference && '' !== $reference ? $reference : (string) $productSaleElementsId;
    }

    /**
     * What names the component of an item in a sentence: the reference of its product, the id of the item when the
     * item or its sale element is gone.
     */
    public static function itemLabel(int $bundleItemId): string
    {
        return self::referenceOfTheComponent($bundleItemId) ?? (string) $bundleItemId;
    }

    /**
     * The reference of the product of the component of an item, null when the item or its sale element is gone.
     */
    public static function referenceOfTheComponent(int $bundleItemId): ?string
    {
        /** @var string|null $reference a single selected column */
        $reference = ProductBundleItemQuery::create()
            ->filterById($bundleItemId)
            ->useProductSaleElementsQuery()
                ->joinProduct()
            ->endUse()
            ->withColumn('Product.Ref', 'ComponentReference')
            ->select(['ComponentReference'])
            ->findOne();

        return null !== $reference && '' !== $reference ? $reference : null;
    }

    private static function referenceOfTheProduct(int $productId): ?string
    {
        /** @var string|null $reference a single selected column */
        $reference = ProductQuery::create()
            ->filterById($productId)
            ->select('Ref')
            ->findOne();

        return null !== $reference && '' !== $reference ? $reference : null;
    }
}
