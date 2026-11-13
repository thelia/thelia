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
 * The unit of the period of a plan, stored as its value in subscription_plan.period_unit
 * and copied to subscription.period_unit.
 */
enum PeriodUnit: string
{
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $unit): string => $unit->value, self::cases());
    }

    /** How many months one unit lasts; null for the week, counted in days. */
    public function months(): ?int
    {
        return match ($this) {
            self::Week => null,
            self::Month => 1,
            self::Quarter => 3,
            self::Year => 12,
        };
    }
}
