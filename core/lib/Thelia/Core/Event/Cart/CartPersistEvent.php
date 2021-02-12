<?php

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
    /** @var Cart $cart */
    protected $cart;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
    }

    /**
     * @return Cart
     */
    public function getCart()
    {
        return $this->cart;
    }

    /**
     * @param Cart $cart
     */
    public function setCart($cart)
    {
        $this->cart = $cart;

        return $this;
    }

    /**
     * check if cart exists.
     *
     * @return bool
     */
    public function hasCart()
    {
        return null !== $this->cart;
    }
}
