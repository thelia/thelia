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
use Thelia\Model\CartItem;

/**
 * Class CartItemDuplicationItem.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CartItemDuplicationItem extends ActionEvent
{
    public function __construct(protected CartItem $newItem, protected CartItem $oldItem)
    {
    }

    public function getNewItem(): CartItem
    {
        return $this->newItem;
    }

    public function getOldItem(): CartItem
    {
        return $this->oldItem;
    }
}
