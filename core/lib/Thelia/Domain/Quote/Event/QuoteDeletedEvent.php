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
 * The shop deleted a quote it never sent. The quote of the event is gone from the database: it
 * still holds what it held (its reference, its customer, its state), and nothing else of it can be
 * read. The revision is the one the quote stood at.
 */
final class QuoteDeletedEvent extends QuoteEvent
{
}
