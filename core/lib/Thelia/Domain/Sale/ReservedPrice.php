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

namespace Thelia\Domain\Sale;

/**
 * The price one reserved operation gives one sale element, for the customer it was
 * resolved for.
 *
 * The price is untaxed, like every price in `product_price` and in `cart_item`:
 * it is written straight into the cart line, and the tax is added on the way out
 * by the same calculator as any other price.
 *
 * The operation is carried along because whoever shows the price also shows where
 * it comes from — its end date for the countdown, and whether the shopkeeper asked
 * for the original price to be struck through next to it.
 */
final readonly class ReservedPrice
{
    public function __construct(
        public int $productSaleElementsId,
        public float $untaxedPromoPrice,
        public int $saleId,
        public ?\DateTimeInterface $saleEndDate,
        public bool $displayInitialPrice,
    ) {
    }
}
