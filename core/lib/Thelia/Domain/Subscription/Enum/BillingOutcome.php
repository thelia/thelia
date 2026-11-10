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

namespace Thelia\Domain\Subscription\Enum;

/**
 * What a billing run did to a subscription (SubscriptionInstallmentBiller::bill()).
 */
enum BillingOutcome: string
{
    /** Another billing of the same subscription holds the lock. */
    case Busy = 'busy';

    /** Missing, not in a state that is billed, or nothing is due yet. */
    case NothingDue = 'nothing_due';

    /** The installment was paid already: nothing charged twice. */
    case AlreadyPaid = 'already_paid';

    /** A cancelled subscription reached its end, or every installment was billed: ended without a charge. */
    case Ended = 'ended';

    case Paid = 'paid';

    /** Declined, or not placeable: tried again later. */
    case Failed = 'failed';

    /** The provider could not be reached: tried again within the hour, not counted as declined. */
    case Retryable = 'retryable';

    /** The declined charges ran out: suspended, the order of the installment cancelled. */
    case Suspended = 'suspended';
}
