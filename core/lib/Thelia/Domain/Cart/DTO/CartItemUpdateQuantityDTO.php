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

namespace Thelia\Domain\Cart\DTO;

use Thelia\Domain\Shared\Contract\DTOEventActionInterface;
use Thelia\Model\Cart;

readonly class CartItemUpdateQuantityDTO implements DTOEventActionInterface, CartDTOInterface
{
    public function __construct(
        private Cart $cart,
        private int $cartItemId,
        private int $quantity,
    ) {
    }

    public function toArray(): array
    {
        return [
            'cart' => $this->cart,
            'cart_item_id' => $this->cartItemId,
            'quantity' => $this->quantity,
        ];
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
