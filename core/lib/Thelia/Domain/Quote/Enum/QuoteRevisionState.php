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
 * Whether a revision of a quote can still be changed.
 */
enum QuoteRevisionState: string
{
    /** Open to changes; the buyer does not see it. */
    case Draft = 'draft';

    /** Final: it was sent, and no one changes it any more. */
    case Sent = 'sent';
}
