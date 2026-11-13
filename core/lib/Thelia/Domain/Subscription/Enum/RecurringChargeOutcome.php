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

enum RecurringChargeOutcome: string
{
    /** The provider took the money. */
    case Paid = 'paid';

    /** The provider refused the charge (insufficient funds, card expired or blocked). */
    case Declined = 'declined';

    /** The issuer asks for the customer (strong authentication): the customer must act. */
    case ActionRequired = 'action_required';

    /** The provider could not be reached or answered an error: the same charge can be tried again. */
    case Retryable = 'retryable';
}
