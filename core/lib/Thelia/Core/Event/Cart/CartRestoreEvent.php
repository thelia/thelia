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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Cart;

class CartRestoreEvent extends ActionEvent
{
    protected ?Cart $cart = null;

    protected bool $linesRemoved = false;

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
    }

    /**
     * Said by the listener that restored the cart when it took lines out of it: the session
     * tells what depends on the lines once it holds the cart (TheliaEvents::CART_LINES_REMOVED).
     */
    public function markLinesRemoved(): static
    {
        $this->linesRemoved = true;

        return $this;
    }

    public function haveLinesBeenRemoved(): bool
    {
        return $this->linesRemoved;
    }
}
