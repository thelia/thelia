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

namespace Thelia\Core\Event\Tax;

use Thelia\Model\Cart;

/**
 * Carries the tax calculator to use for the lines of one cart.
 *
 * Dispatched as TheliaEvents::TAX_GET_CART_CALCULATOR by call sites that have
 * no access to the container, typically Propel models. It differs from
 * TaxCalculatorEvent by naming the cart being priced, which is what lets a
 * listener answer differently for a buyer who accounts for the VAT himself
 * without having to read any ambient state - and therefore without touching
 * the catalogue prices, which are computed for nobody in particular.
 *
 * Thelia answers it last, so any listener that sets a calculator wins over the
 * default one.
 */
class CartTaxCalculatorEvent extends TaxCalculatorEvent
{
    public function __construct(private readonly Cart $cart)
    {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
