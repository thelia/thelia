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

namespace Thelia\Domain\Company\Service;

use Thelia\Domain\Company\Exception\InvalidCompanyDataException;

/**
 * What a buyer's limits may hold: amounts of zero or more in the default currency, over a week,
 * a month or a year.
 */
final class CompanyLimits
{
    public const array PERIODS = ['week', 'month', 'year'];

    /**
     * @throws InvalidCompanyDataException
     */
    public static function assertValid(?string $orderLimit, ?string $periodLimit, ?string $period): void
    {
        foreach (['order limit' => $orderLimit, 'period limit' => $periodLimit] as $label => $limit) {
            if (null !== $limit && !CompanyAmount::isValid($limit)) {
                throw new InvalidCompanyDataException(\sprintf('The %s must be an amount of zero or more, with six decimals at most.', $label));
            }
        }

        if (null !== $period && !\in_array($period, self::PERIODS, true)) {
            throw new InvalidCompanyDataException('The period is week, month or year.');
        }

        if (null !== $periodLimit && null === $period) {
            throw new InvalidCompanyDataException('A limit over a period needs the period it counts over.');
        }
    }
}
