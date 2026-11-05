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

namespace Thelia\Domain\Order\Service;

/**
 * One quantity a movement takes from the stock of a sale element: the national counter, or the
 * own stock of the sale unit of the movement when it keeps one.
 */
final readonly class StockTake
{
    /**
     * @param bool  $guardAvailability    whether an insufficient stock refuses the take (see StockDecrementer)
     * @param float $reservedByOtherCarts what the carts other than the one being ordered hold of the same stock,
     *                                    read after its row was locked; left to them by the guard
     */
    public function __construct(
        public int $productSaleElementsId,
        public float $quantity,
        public bool $guardAvailability,
        public float $reservedByOtherCarts = 0.0,
    ) {
    }
}
