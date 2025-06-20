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

/**
 * Class CartPersistEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class CartPersistEvent extends ActionEvent
{
    public function __construct(protected Cart $cart)
    {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
    }

    /**
     * check if cart exists.
     */
    public function hasCart(): bool
    {
        return $this->cart instanceof Cart;
    }
}
