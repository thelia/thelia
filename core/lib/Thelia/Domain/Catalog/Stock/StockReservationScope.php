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

namespace Thelia\Domain\Catalog\Stock;

/** When the stock of a cart is held. */
enum StockReservationScope: string
{
    /** From the start of the checkout: the default. */
    case Checkout = 'checkout';

    /** As soon as the item is in the cart. */
    case Cart = 'cart';
}
