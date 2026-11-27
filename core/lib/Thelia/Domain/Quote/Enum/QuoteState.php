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

namespace Thelia\Domain\Quote\Enum;

/**
 * Where a quote stands between the request of the buyer and the order.
 */
enum QuoteState: string
{
    /** Written by the shop for a buyer, not yet sent. */
    case Draft = 'draft';

    /** Asked for by the buyer, waiting for the shop. */
    case Requested = 'requested';

    /** Sent to the buyer, who may accept it, counter it or turn it down. */
    case Quoted = 'quoted';

    /** The buyer answered the last revision with a counter-proposal. */
    case Countered = 'countered';

    /** The buyer accepted a revision and has yet to place the order. */
    case Accepted = 'accepted';

    /** The buyer turned the quote down. */
    case Rejected = 'rejected';

    /** The validity of the quote ran out before it was accepted. */
    case Expired = 'expired';

    /** The order of the accepted quote was placed. */
    case Ordered = 'ordered';
}
