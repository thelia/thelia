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
 * The shop started a quote for a customer, with no request behind it. The revision is the draft
 * the shop writes first.
 */
final class QuoteCreatedEvent extends QuoteEvent
{
}
