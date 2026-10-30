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

use Thelia\Domain\Cart\Exception\InvalidCartException;

/**
 * A composition a cart line cannot hold: refused like any cart line the cart cannot take.
 *
 * The message is read by the buyer (the product sheet of a theme shows it, the API answers it
 * with a 422): a translated sentence that names the component by the reference of its product,
 * the one printed on the catalogue, never by an id. The ids stay on the exception for a caller
 * that needs them, and the technical reason of a malformed composition with them.
 */
final class InvalidBundleCompositionException extends InvalidCartException
{
    private function __construct(
        string $message,
        public readonly ?int $productId = null,
        public readonly ?int $bundleItemId = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message);
    }

    public static function notABundle(int $productId): self
    {
        return new self(BundleExceptionMessage::translate('This product is sold on its own: it takes no choice of components.'), productId: $productId);
    }

    /**
     * @param string $reason what is wrong with it, for a log or a developer; the buyer reads a sentence of their own
     */
    public static function malformed(string $reason): self
    {
        return new self(BundleExceptionMessage::translate('The choice of components of this bundle cannot be read.'), reason: $reason);
    }

    public static function unknownItem(int $bundleItemId, int $bundleProductId): self
    {
        return new self(BundleExceptionMessage::translate('A component chosen is not part of this bundle.'), productId: $bundleProductId, bundleItemId: $bundleItemId);
    }

    public static function quantityOutOfBounds(int $bundleItemId, string $quantity, string $lowest, string $highest): self
    {
        $reference = BundleExceptionMessage::referenceOfTheComponent($bundleItemId);

        $message = null === $reference
            ? BundleExceptionMessage::translate('A quantity chosen is outside what this bundle allows: between %min and %max.', ['%min' => $lowest, '%max' => $highest])
            : BundleExceptionMessage::translate('Choose between %min and %max of product %ref in this bundle.', ['%min' => $lowest, '%max' => $highest, '%ref' => $reference]);

        return new self($message, bundleItemId: $bundleItemId, reason: \sprintf('quantity %s out of [%s, %s]', $quantity, $lowest, $highest));
    }

    public static function requiredItemMissing(int $bundleItemId): self
    {
        $reference = BundleExceptionMessage::referenceOfTheComponent($bundleItemId);

        $message = null === $reference
            ? BundleExceptionMessage::translate('A component this bundle requires is missing from the choice.')
            : BundleExceptionMessage::translate('Product %ref is required in this bundle.', ['%ref' => $reference]);

        return new self($message, bundleItemId: $bundleItemId);
    }

    public static function componentNotSellable(int $bundleItemId): self
    {
        $reference = BundleExceptionMessage::referenceOfTheComponent($bundleItemId);

        $message = null === $reference
            ? BundleExceptionMessage::translate('A component of this bundle cannot be sold at the moment.')
            : BundleExceptionMessage::translate('Product %ref of this bundle cannot be sold at the moment.', ['%ref' => $reference]);

        return new self($message, bundleItemId: $bundleItemId);
    }

    public static function bundleHoldsNothing(int $bundleProductId): self
    {
        return new self(BundleExceptionMessage::translate('This bundle has no component to sell: it cannot be added to a cart.'), productId: $bundleProductId);
    }
}
