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
 * The shop wrote the draft of its next revision. The revision is the draft, which the buyer does
 * not see until it is sent.
 */
final class QuoteDraftSavedEvent extends QuoteEvent
{
}
