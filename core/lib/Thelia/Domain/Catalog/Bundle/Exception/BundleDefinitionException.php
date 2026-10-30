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

/**
 * A change of the definition of a bundle (its components, its price settings) the catalogue refuses.
 *
 * The message is read by whoever edits the bundle (the back office, the answer of the admin API): a translated
 * sentence that names a product by its reference, the one printed on the catalogue, and by an id only where
 * nothing else is left to name (the product, the item or the sale element asked for does not exist).
 */
final class BundleDefinitionException extends \DomainException
{
    public static function productNotFound(int $productId): self
    {
        return new self(BundleExceptionMessage::translate('Product %id does not exist.', ['%id' => (string) $productId]));
    }

    public static function itemNotFound(int $bundleItemId): self
    {
        return new self(BundleExceptionMessage::translate('Bundle item %id does not exist.', ['%id' => (string) $bundleItemId]));
    }

    public static function itemStaysInItsBundle(int $bundleItemId, int $bundleProductId): self
    {
        return new self(BundleExceptionMessage::translate(
            'Component %component belongs to bundle %bundle and cannot be moved to another: delete it and add a new one.',
            ['%component' => BundleExceptionMessage::itemLabel($bundleItemId), '%bundle' => BundleExceptionMessage::productLabel($bundleProductId)],
        ));
    }

    public static function itemKeepsItsSaleElement(int $bundleItemId): self
    {
        return new self(BundleExceptionMessage::translate(
            'Component %component keeps its sale element: delete it and add a new one.',
            ['%component' => BundleExceptionMessage::itemLabel($bundleItemId)],
        ));
    }

    public static function saleElementsNotFound(int $productSaleElementsId): self
    {
        return new self(BundleExceptionMessage::translate('Sale element %id does not exist.', ['%id' => (string) $productSaleElementsId]));
    }

    public static function notABundle(int $productId): self
    {
        return new self(BundleExceptionMessage::translate(
            'Product %ref is not a bundle: turn it into one before adding components.',
            ['%ref' => BundleExceptionMessage::productLabel($productId)],
        ));
    }

    public static function componentIsTheBundle(int $productId): self
    {
        return new self(BundleExceptionMessage::translate(
            'Product %ref cannot be a component of itself.',
            ['%ref' => BundleExceptionMessage::productLabel($productId)],
        ));
    }

    public static function componentIsABundle(int $productId): self
    {
        return new self(BundleExceptionMessage::translate(
            'Product %ref is a bundle: a bundle cannot hold another bundle.',
            ['%ref' => BundleExceptionMessage::productLabel($productId)],
        ));
    }

    public static function bundleIsAComponent(int $productId): self
    {
        return new self(BundleExceptionMessage::translate(
            'Product %ref is a component of a bundle: it cannot become a bundle.',
            ['%ref' => BundleExceptionMessage::productLabel($productId)],
        ));
    }

    public static function hasSeveralSaleElements(int $productId, int $count): self
    {
        return new self(BundleExceptionMessage::translate(
            'Product %ref has %count sale elements: a bundle has a single one, so it cannot become a bundle.',
            ['%ref' => BundleExceptionMessage::productLabel($productId), '%count' => (string) $count],
        ));
    }

    public static function bundleHasOneSaleElement(int $productId): self
    {
        return new self(BundleExceptionMessage::translate(
            'Product %ref is a bundle: it has a single sale element and no other can be created.',
            ['%ref' => BundleExceptionMessage::productLabel($productId)],
        ));
    }

    public static function componentAlreadyInBundle(int $productSaleElementsId, int $bundleProductId): self
    {
        return new self(BundleExceptionMessage::translate(
            'Product %component is already a component of bundle %bundle.',
            ['%component' => BundleExceptionMessage::saleElementsLabel($productSaleElementsId), '%bundle' => BundleExceptionMessage::productLabel($bundleProductId)],
        ));
    }

    public static function invalidQuantity(string $reason): self
    {
        return new self(\sprintf('Invalid bundle quantity: %s.', $reason));
    }

    public static function invalidSetting(string $reason): self
    {
        return new self(\sprintf('Invalid bundle setting: %s.', $reason));
    }
}
