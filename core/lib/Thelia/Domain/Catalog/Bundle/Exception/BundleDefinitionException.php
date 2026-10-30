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
 */
final class BundleDefinitionException extends \DomainException
{
    public static function productNotFound(int $productId): self
    {
        return new self(\sprintf('Product %d does not exist.', $productId));
    }

    public static function itemNotFound(int $bundleItemId): self
    {
        return new self(\sprintf('Bundle item %d does not exist.', $bundleItemId));
    }

    public static function itemStaysInItsBundle(int $bundleItemId, int $bundleProductId): self
    {
        return new self(\sprintf('Bundle item %d belongs to bundle %d and cannot be moved to another: delete it and add a new one.', $bundleItemId, $bundleProductId));
    }

    public static function itemKeepsItsSaleElement(int $bundleItemId): self
    {
        return new self(\sprintf('Bundle item %d keeps its sale element: delete it and add a new one.', $bundleItemId));
    }

    public static function saleElementsNotFound(int $productSaleElementsId): self
    {
        return new self(\sprintf('Sale element %d does not exist.', $productSaleElementsId));
    }

    public static function notABundle(int $productId): self
    {
        return new self(\sprintf('Product %d is not a bundle: turn it into one before adding components.', $productId));
    }

    public static function componentIsTheBundle(int $productId): self
    {
        return new self(\sprintf('Product %d cannot be a component of itself.', $productId));
    }

    public static function componentIsABundle(int $productId): self
    {
        return new self(\sprintf('Product %d is a bundle: a bundle cannot hold another bundle.', $productId));
    }

    public static function bundleIsAComponent(int $productId): self
    {
        return new self(\sprintf('Product %d is a component of a bundle: it cannot become a bundle.', $productId));
    }

    public static function hasSeveralSaleElements(int $productId, int $count): self
    {
        return new self(\sprintf('Product %d has %d sale elements: a bundle has a single one, so it cannot become a bundle.', $productId, $count));
    }

    public static function bundleHasOneSaleElement(int $productId): self
    {
        return new self(\sprintf('Product %d is a bundle: it has a single sale element and no other can be created.', $productId));
    }

    public static function componentAlreadyInBundle(int $productSaleElementsId, int $bundleProductId): self
    {
        return new self(\sprintf('Sale element %d is already a component of bundle %d.', $productSaleElementsId, $bundleProductId));
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
