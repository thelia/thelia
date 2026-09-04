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

namespace Thelia\Domain\Checkout\Service;

use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductQuery;

/**
 * Answers the one question the whole guest checkout hangs off: may this cart be
 * ordered without an account?
 *
 * The front office, the API and the back office all ask here rather than reading the
 * setting themselves, so that a shop only ever has one answer to give.
 */
final readonly class GuestCheckoutPolicy
{
    public function mode(): GuestCheckoutMode
    {
        return GuestCheckoutMode::fromStoredValue(ConfigQuery::getGuestCheckoutMode());
    }

    /**
     * Whether the shop offers the guest checkout at all, whatever is in the cart.
     *
     * Use this to decide whether to show the option anywhere a cart is not in hand;
     * a cart in hand is answered by isGuestCheckoutAllowedForCart().
     */
    public function isGuestCheckoutEnabled(): bool
    {
        return GuestCheckoutMode::Disabled !== $this->mode();
    }

    /**
     * @throws PropelException
     */
    public function isGuestCheckoutAllowedForCart(Cart $cart): bool
    {
        return match ($this->mode()) {
            GuestCheckoutMode::Disabled => false,
            GuestCheckoutMode::Enabled => true,
            GuestCheckoutMode::EnabledUnlessProductForbids => !$this->cartHoldsAForbiddenProduct($cart),
        };
    }

    /**
     * @throws PropelException
     */
    private function cartHoldsAForbiddenProduct(Cart $cart): bool
    {
        $productIds = [];

        foreach ($cart->getCartItems() as $cartItem) {
            $productIds[] = $cartItem->getProductId();
        }

        $productIds = array_values(array_unique(array_filter($productIds)));

        if ([] === $productIds) {
            return false;
        }

        return ProductQuery::create()
            ->filterById($productIds)
            ->filterByGuestCheckoutForbidden(1)
            ->exists();
    }
}
