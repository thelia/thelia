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

namespace Thelia\Domain\Ai;

/**
 * The status column of the usage log.
 */
enum AiUsageLogStatus: string
{
    case Succeeded = 'succeeded';
    /** The provider failed or answered something the gateway could not read. */
    case Failed = 'failed';
    /** A guardrail in block mode stopped the request or the response. */
    case Blocked = 'blocked';
    /** Refused before any call: inactive usage, budget, rate limit, jurisdiction. Costs nothing. */
    case Rejected = 'rejected';
    /** The caller stopped reading a stream before its end. The tokens are those known at that moment. */
    case Aborted = 'aborted';
}
