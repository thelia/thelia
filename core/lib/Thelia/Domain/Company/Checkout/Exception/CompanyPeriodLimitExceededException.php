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

namespace Thelia\Domain\Company\Checkout\Exception;

/**
 * What the buyer already spent over the period, with this order, goes over what they may spend.
 */
final class CompanyPeriodLimitExceededException extends CompanyOrderRefusedException
{
    public const string VIOLATION_CODE = 'company-period-limit-exceeded';

    /**
     * @param string                                                $limit  the most the buyer may spend over the period, in the default currency
     * @param string                                                $period week, month or year
     * @param string                                                $spent  what the orders of the period already cost, in the default currency
     * @param string                                                $total  what this order costs, in the default currency
     * @param array{limit?: string, spent?: string, total?: string} $shown  the amounts as the buyer reads them in the message, with their currency
     */
    public function __construct(
        public readonly string $limit,
        public readonly string $period,
        public readonly string $spent,
        public readonly string $total,
        ?\Throwable $previous = null,
        array $shown = [],
    ) {
        parent::__construct(
            'This order of %total, added to the %spent already ordered over the period, exceeds the %limit your company allows.',
            0,
            $previous,
            ['%total' => $shown['total'] ?? $total, '%spent' => $shown['spent'] ?? $spent, '%limit' => $shown['limit'] ?? $limit],
        );
    }

    public function violationCode(): string
    {
        return self::VIOLATION_CODE;
    }

    public function violationDetails(): array
    {
        return ['limit' => $this->limit, 'period' => $this->period, 'spent' => $this->spent, 'total' => $this->total];
    }
}
