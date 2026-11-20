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
 * Each customer who ordered up to the end of the period is in one segment. New: the first order of
 * the customer falls in the period. Returning: the customer ordered in the period and before it.
 * Lapsing: no order in the period, the last one within the dormancy threshold at its end. Dormant:
 * no order in the period, the last one older than the threshold.
 */
enum CustomerSegment: string
{
    case New = 'new';
    case Returning = 'returning';
    case Lapsing = 'lapsing';
    case Dormant = 'dormant';
}
