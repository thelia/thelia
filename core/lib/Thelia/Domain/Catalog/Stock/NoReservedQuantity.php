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

/** The provider of a shop that reserves nothing: every unit of the stock is for whoever asks first. */
final readonly class NoReservedQuantity implements ReservedQuantityProvider
{
    public function reservedByOtherCarts(int $productSaleElementsId, ?int $saleUnitId, ?int $excludedCartId): float
    {
        return 0.0;
    }

    public function reservedByOtherCartsOf(array $productSaleElementsIds, ?int $saleUnitId, ?int $excludedCartId): array
    {
        return [];
    }
}
