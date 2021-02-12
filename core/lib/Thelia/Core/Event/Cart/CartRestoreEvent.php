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

class CartRestoreEvent extends ActionEvent
{
    protected $cart;

    /**
     * @return \Thelia\Model\Cart
     */
    public function getCart()
    {
        return $this->cart;
    }

    /**
     * @return $this
     */
    public function setCart(Cart $cart)
    {
        $this->cart = $cart;

        return $this;
    }
}
