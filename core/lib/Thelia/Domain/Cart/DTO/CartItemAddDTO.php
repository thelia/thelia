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

readonly class CartItemAddDTO implements DTOEventActionInterface, CartDTOInterface
{
    public function __construct(
        private Cart $cart,
        private int $productId,
        private int $productSaleElementId,
        private int $quantity = 1,
        private bool $append = true,
        private bool $newness = true,
    ) {
    }

    public function toArray(): array
    {
        return [
            'cart' => $this->cart,
            'productId' => $this->productId,
            'productSaleElementsId' => $this->productSaleElementId,
            'quantity' => $this->quantity,
            'append' => $this->append,
            'newness' => $this->newness,
        ];
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
