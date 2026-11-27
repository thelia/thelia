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
 * The names the quote events are dispatched under.
 */
final class QuoteEvents
{
    public const string REQUESTED = 'action.quote.requested';

    public const string CREATED = 'action.quote.created';

    public const string DRAFT_SAVED = 'action.quote.draft_saved';

    public const string DUPLICATED = 'action.quote.duplicated';

    public const string DELETED = 'action.quote.deleted';

    public const string SENT = 'action.quote.sent';

    public const string COUNTERED = 'action.quote.countered';

    public const string ACCEPTED = 'action.quote.accepted';

    public const string REJECTED = 'action.quote.rejected';

    public const string EXTENDED = 'action.quote.extended';

    public const string REMINDED = 'action.quote.reminded';

    public const string EXPIRED = 'action.quote.expired';

    public const string ORDERED = 'action.quote.ordered';

    private function __construct()
    {
    }
}
