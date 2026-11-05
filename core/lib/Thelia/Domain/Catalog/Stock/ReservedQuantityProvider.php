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

/**
 * The units of a target that carts other than the one asking hold, still, under a reservation.
 *
 * The target is a sale element, or a sale element of a sale unit when the cart has one. A
 * reservation that has expired is never counted, whether or not a purge has removed it.
 */
interface ReservedQuantityProvider
{
    /**
     * @param int|null $excludedCartId the cart asking: what it holds itself is never taken from what it may buy
     */
    public function reservedByOtherCarts(int $productSaleElementsId, ?int $saleUnitId, ?int $excludedCartId): float;

    /**
     * The same answer for several sale elements, in one read.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, float> by sale element id; a sale element nothing holds is absent
     */
    public function reservedByOtherCartsOf(array $productSaleElementsIds, ?int $saleUnitId, ?int $excludedCartId): array;
}
