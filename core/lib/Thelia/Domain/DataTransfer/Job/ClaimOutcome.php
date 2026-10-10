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

namespace Thelia\Domain\DataTransfer\Job;

/**
 * What became of a message that tried to take its job.
 */
enum ClaimOutcome
{
    /** This run owns the job and runs it. */
    case Owned;

    /** The job is over, or no queue can look at it again: nothing to do. */
    case Finished;

    /** Another run holds the job: the message looks at it again later. */
    case Postponed;
}
