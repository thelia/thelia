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

namespace Thelia\Domain\Report\Customer;

/**
 * New: the first order of the customer falls in the period. Returning: the customer ordered in the
 * period and before it. Dormant: the last order of the customer is older than the dormancy
 * threshold at the end of the period.
 */
enum CustomerSegment: string
{
    case New = 'new';
    case Returning = 'returning';
    case Dormant = 'dormant';
}
