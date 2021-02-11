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
use Thelia\Model\CartItem;

/**
 * Class CartItemDuplicationItem
 * @package Thelia\Core\Event\Cart
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CartItemDuplicationItem extends ActionEvent
{
    /**
     * @var \Thelia\Model\CartItem
     */
    protected $oldItem;

    /**
     * @var \Thelia\Model\CartItem
     */
    protected $newItem;

    public function __construct(CartItem $newItem, CartItem $oldItem)
    {
        $this->newItem = $newItem;
        $this->oldItem = $oldItem;
    }

    /**
     * @return \Thelia\Model\CartItem
     */
    public function getNewItem()
    {
        return $this->newItem;
    }

    /**
     * @return \Thelia\Model\CartItem
     */
    public function getOldItem()
    {
        return $this->oldItem;
    }
}
