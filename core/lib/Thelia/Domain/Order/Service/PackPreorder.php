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
 * What a pack freezes of the preorder of its components, wherever their stock is taken (the
 * placement of the order, or its payment): a preorder when one of them is, waiting for the
 * latest of their restock dates, the day the whole pack can ship.
 */
final class PackPreorder
{
    /**
     * Components restocked on 2026-12-01 and 2026-12-15, the third in stock: a preorder until 2026-12-15.
     *
     * @param iterable<StockMovement|null> $componentMovements what the take of each component did, null when it moved nothing
     *
     * @return array{isPreorder: bool, restockDate: ?string}
     */
    public static function of(iterable $componentMovements): array
    {
        $dates = [];
        $isPreorder = false;

        foreach ($componentMovements as $movement) {
            if (null === $movement || !$movement->preorder) {
                continue;
            }

            $isPreorder = true;

            if (null !== $movement->restockDate) {
                $dates[] = $movement->restockDate;
            }
        }

        return ['isPreorder' => $isPreorder, 'restockDate' => [] === $dates ? null : max($dates)];
    }
}
