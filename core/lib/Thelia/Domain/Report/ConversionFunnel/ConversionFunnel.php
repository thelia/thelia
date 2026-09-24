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

final readonly class ConversionFunnel
{
    /**
     * @param list<FunnelStep> $steps in the order of the funnel, from the created carts to the paid orders
     */
    public function __construct(public array $steps)
    {
    }

    public static function fromCounts(
        int $cartsCreated,
        int $cartsWithItems,
        int $cartsWithDelivery,
        int $cartsWithPayment,
        int $ordersCreated,
        int $ordersPaid,
    ): self {
        $counts = [
            FunnelStep::CARTS_CREATED => $cartsCreated,
            FunnelStep::CARTS_WITH_ITEMS => $cartsWithItems,
            FunnelStep::CARTS_WITH_DELIVERY => $cartsWithDelivery,
            FunnelStep::CARTS_WITH_PAYMENT => $cartsWithPayment,
            FunnelStep::ORDERS_CREATED => $ordersCreated,
            FunnelStep::ORDERS_PAID => $ordersPaid,
        ];

        $steps = [];
        $previousCount = null;
        foreach ($counts as $key => $count) {
            $steps[] = new FunnelStep($key, $count, $previousCount, $cartsWithItems);
            $previousCount = $count;
        }

        return new self($steps);
    }

    public function step(string $key): FunnelStep
    {
        foreach ($this->steps as $step) {
            if ($step->key === $key) {
                return $step;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Unknown conversion funnel step "%s".', $key));
    }

    public function conversionRate(): ?float
    {
        return $this->step(FunnelStep::ORDERS_PAID)->ratioToBase();
    }

    public function conversionRatePercent(): ?float
    {
        return FunnelStep::percent($this->conversionRate());
    }
}
