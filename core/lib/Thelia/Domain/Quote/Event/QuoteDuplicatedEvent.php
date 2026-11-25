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

use Thelia\Model\Quote;

/**
 * The shop copied a quote into a new one. The quote of the event is the copy, a draft whose
 * revision is its first; the original is the quote it was taken from, left as it was.
 */
final class QuoteDuplicatedEvent extends QuoteEvent
{
    public function __construct(
        Quote $copy,
        private readonly Quote $original,
        int $revisionNumber,
    ) {
        parent::__construct($copy, $revisionNumber);
    }

    public function getOriginal(): Quote
    {
        return $this->original;
    }
}
