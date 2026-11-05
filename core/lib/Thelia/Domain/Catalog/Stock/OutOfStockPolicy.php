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

/** What happens when a sale element is asked above its physical stock. */
enum OutOfStockPolicy: int
{
    /** The sale is refused: the historical behaviour. */
    case Refuse = 0;

    /** The sale goes on whatever the stock, which may go below zero. */
    case Unlimited = 1;

    /** The sale goes on until the stock is `preorder_limit` units below zero. */
    case Limited = 2;

    /** A stored value that is missing or unknown behaves as the default, refuse. */
    public static function fromStored(?int $value): self
    {
        return null === $value ? self::Refuse : (self::tryFrom($value) ?? self::Refuse);
    }
}
