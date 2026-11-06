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

/** The stock of one sale element before and after a change. */
final readonly class StockLevelChange
{
    public function __construct(
        public int $productSaleElementsId,
        public float $previousQuantity,
        public float $newQuantity,
    ) {
    }
}
