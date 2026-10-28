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

namespace Thelia\Domain\Ai\Tool;

enum AiToolApprovalStatus: string
{
    case Pending = 'pending';
    /** Decided and being applied: the row stays here when the process dies before it says how it ended. */
    case Applying = 'applying';
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Expired = 'expired';
}
