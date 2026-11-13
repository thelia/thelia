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
 * Where an installment stands, stored in subscription_order.state.
 */
enum SubscriptionOrderState: string
{
    /** Recorded, its order not charged yet. */
    case Pending = 'pending';

    /** Its order is paid: the installment is never charged again. */
    case Paid = 'paid';

    /** Its last charge did not go through; a retry charges the same order again. */
    case Failed = 'failed';
}
