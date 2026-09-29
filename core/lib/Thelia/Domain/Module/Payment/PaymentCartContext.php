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

namespace Thelia\Domain\Module\Payment;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Thelia\Model\Cart;

#[Autoconfigure(public: true)]
final class PaymentCartContext
{
    private ?Cart $cart = null;

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    public function within(Cart $cart, callable $call): mixed
    {
        $previousCart = $this->cart;
        $this->cart = $cart;

        try {
            return $call();
        } finally {
            $this->cart = $previousCart;
        }
    }

    public function cart(): ?Cart
    {
        return $this->cart;
    }
}
