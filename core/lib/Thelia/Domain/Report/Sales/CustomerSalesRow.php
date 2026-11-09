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
 * What a customer spent over a period: the totals of its orders as they were charged,
 * discount and postage included.
 */
final readonly class CustomerSalesRow
{
    public function __construct(
        public int $customerId,
        public string $ref,
        public string $firstname,
        public string $lastname,
        public string $email,
        public int $orderCount,
        public float $total,
        public float $averageBasket,
        public \DateTimeImmutable $lastOrderAt,
        public float $previousTotal,
    ) {
    }
}
