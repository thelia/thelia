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

namespace Thelia\Domain\QuickOrder\Enum;

/**
 * What the quick order made of one line the buyer typed or imported.
 */
enum LineStatus: string
{
    /** One sale element, priced and in stock: the line can go to the cart. */
    case Resolved = 'resolved';

    /** Several sale elements carry the reference: the buyer picks one. */
    case Ambiguous = 'ambiguous';

    /** Nothing the buyer may order carries the reference, hidden products included. */
    case Unknown = 'unknown';

    /** The sale element exists but cannot be sold: out of stock, or without a price. */
    case Unavailable = 'unavailable';

    /** The quantity asked for is more than the stock holds. */
    case QuantityRefused = 'quantity_refused';
}
