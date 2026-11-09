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

namespace Thelia\Domain\Report\Sales;

/**
 * The orders a sales report reads: the ones the shop was paid for (a status answering for
 * paid, processing or sent, the statuses of the shop included through their equivalent
 * code), or every order created in the period.
 */
enum StatusScope: string
{
    case Paid = 'paid';
    case All = 'all';
}
