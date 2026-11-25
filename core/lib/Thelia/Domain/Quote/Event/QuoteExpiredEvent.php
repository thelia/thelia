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

namespace Thelia\Domain\Quote\Event;

/**
 * The last day of validity of the quote went by without an answer: the quote was written as
 * expired.
 */
final class QuoteExpiredEvent extends QuoteEvent
{
}
