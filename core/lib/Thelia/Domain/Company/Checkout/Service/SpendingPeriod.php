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

namespace Thelia\Domain\Company\Checkout\Service;

/**
 * The calendar period a buyer's limit counts over: the week from Monday, the month or the year
 * the order is placed in, in the timezone of the shop (the one PHP runs in, which is the one
 * the dates of the orders are written in).
 */
enum SpendingPeriod: string
{
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /**
     * The first second of the period $now falls in.
     */
    public function startAt(\DateTimeImmutable $now): \DateTimeImmutable
    {
        $today = $now->setTime(0, 0);

        return match ($this) {
            self::Week => $today->modify('monday this week'),
            self::Month => $today->modify('first day of this month'),
            self::Year => $today->setDate((int) $today->format('Y'), 1, 1),
        };
    }
}
