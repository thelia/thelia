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

namespace Thelia\Domain\Report\ConversionFunnel;

final readonly class DailyFunnelRow
{
    public function __construct(
        public \DateTimeImmutable $day,
        public int $cartsCreated,
        public int $cartsWithItems,
        public int $cartsWithDelivery,
        public int $cartsWithPayment,
        public int $ordersCreated,
        public int $ordersPaid,
    ) {
    }

    public function conversionRatePercent(): ?float
    {
        if (0 === $this->cartsWithItems) {
            return null;
        }

        return FunnelStep::percent($this->ordersPaid / $this->cartsWithItems);
    }
}
