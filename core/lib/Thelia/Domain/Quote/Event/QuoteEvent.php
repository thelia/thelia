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

use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Model\Quote;

/**
 * Something happened to a quote, and it is written: sent once the transaction is committed.
 */
abstract class QuoteEvent extends Event
{
    public function __construct(
        private readonly Quote $quote,
        private readonly int $revisionNumber,
    ) {
    }

    public function getQuote(): Quote
    {
        return $this->quote;
    }

    /**
     * The revision the event is about: the one that was requested, sent, countered, accepted,
     * rejected, extended, reminded, expired or ordered.
     */
    public function getRevisionNumber(): int
    {
        return $this->revisionNumber;
    }
}
