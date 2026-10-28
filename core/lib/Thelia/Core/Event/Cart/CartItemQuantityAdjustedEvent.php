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

namespace Thelia\Core\Event\Cart;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Core\Event\ActionEvent;
use Thelia\Domain\Catalog\Quantity\QuantityDecision;
use Thelia\Model\CartItem;

/**
 * A line of a cart read back no longer met the rules of its combination (a cart
 * restored, rules changed since, a stock cut short) and was brought down to the valid
 * quantity below it, or removed when even that is below the smallest quantity sold.
 * Dispatched once the line is saved or deleted, for a theme to tell the buyer why
 * (getMessage()) rather than guess it.
 */
#[Exclude]
class CartItemQuantityAdjustedEvent extends ActionEvent
{
    public function __construct(
        private readonly CartItem $cartItem,
        private readonly QuantityDecision $decision,
    ) {
    }

    /**
     * The line as it is now: deleted when isRemoved().
     */
    public function getCartItem(): CartItem
    {
        return $this->cartItem;
    }

    public function getDecision(): QuantityDecision
    {
        return $this->decision;
    }

    public function isRemoved(): bool
    {
        return $this->decision->removesTheLine();
    }

    /**
     * What happened to the line, translated, for the buyer.
     */
    public function getMessage(): string
    {
        return $this->decision->message();
    }
}
