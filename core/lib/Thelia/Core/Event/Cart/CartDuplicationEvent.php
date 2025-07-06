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

use Thelia\Model\Cart;

class CartDuplicationEvent extends CartEvent
{
    public function __construct(Cart $duplicatedCart, protected Cart $originalCart)
    {
        parent::__construct($duplicatedCart);
    }

    public function getDuplicatedCart(): Cart
    {
        return $this->getCart();
    }

    public function getOriginalCart(): Cart
    {
        return $this->originalCart;
    }
}
