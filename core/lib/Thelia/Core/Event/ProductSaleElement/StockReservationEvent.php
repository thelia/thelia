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

namespace Thelia\Core\Event\ProductSaleElement;

use Thelia\Core\Event\ActionEvent;

/**
 * What a cart started or stopped holding of the stock, told once for the cart: its lines, with the
 * quantity of each, in the order of their ids.
 */
abstract class StockReservationEvent extends ActionEvent
{
    /** @param list<StockReservationLine> $lines */
    public function __construct(
        private readonly int $cartId,
        private readonly array $lines,
    ) {
    }

    public function getCartId(): int
    {
        return $this->cartId;
    }

    /** @return list<StockReservationLine> */
    public function getLines(): array
    {
        return $this->lines;
    }
}
