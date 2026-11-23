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
 * Each customer who ordered up to the end of the period is in one segment. Dormant: the last order
 * is older than the dormancy threshold at the end of the period, even if it falls in the period.
 * Otherwise, new: the first order of the customer falls in the period; returning: the customer
 * ordered in the period and before it; lapsing: no order in the period.
 */
enum CustomerSegment: string
{
    case New = 'new';
    case Returning = 'returning';
    case Lapsing = 'lapsing';
    case Dormant = 'dormant';
}
