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
 * The order costs more than the buyer may spend on a single order.
 */
final class CompanyOrderLimitExceededException extends CompanyOrderRefusedException
{
    public const string VIOLATION_CODE = 'company-order-limit-exceeded';

    /**
     * @param string                                $limit the most the buyer may spend on one order, in the default currency
     * @param string                                $total what this order costs, in the default currency
     * @param array{limit?: string, total?: string} $shown the amounts as the buyer reads them in the message, with their currency
     */
    public function __construct(
        public readonly string $limit,
        public readonly string $total,
        ?\Throwable $previous = null,
        array $shown = [],
    ) {
        parent::__construct(
            'This order of %total exceeds the %limit your company allows for a single order.',
            0,
            $previous,
            ['%total' => $shown['total'] ?? $total, '%limit' => $shown['limit'] ?? $limit],
        );
    }

    public function violationCode(): string
    {
        return self::VIOLATION_CODE;
    }

    public function violationDetails(): array
    {
        return ['limit' => $this->limit, 'total' => $this->total];
    }
}
