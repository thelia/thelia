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

namespace Thelia\Domain\Ai\Compliance;

enum ComplianceState: string
{
    case Met = 'met';
    /** The merchant turned off the tool the core provides for this duty. */
    case NotMet = 'not_met';
    /** The declaration of the usage does not say enough: someone has to look. */
    case ToReview = 'to_review';
}
