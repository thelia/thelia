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

/**
 * One step of the conversion funnel, compared to the step before it and to the carts
 * holding at least one line, the base every rate of the funnel is read against.
 */
final readonly class FunnelStep
{
    public const string CARTS_CREATED = 'carts_created';
    public const string CARTS_WITH_ITEMS = 'carts_with_items';
    public const string CARTS_WITH_DELIVERY = 'carts_with_delivery';
    public const string CARTS_WITH_PAYMENT = 'carts_with_payment';
    public const string ORDERS_CREATED = 'orders_created';
    public const string ORDERS_PAID = 'orders_paid';

    public const int PERCENT_PRECISION = 1;

    public function __construct(
        public string $key,
        public int $count,
        public ?int $previousCount,
        public int $baseCount,
    ) {
    }

    public function ratioToPrevious(): ?float
    {
        if (null === $this->previousCount || 0 === $this->previousCount) {
            return null;
        }

        return $this->count / $this->previousCount;
    }

    public function ratioToBase(): ?float
    {
        if (0 === $this->baseCount) {
            return null;
        }

        return $this->count / $this->baseCount;
    }

    public function percentToPrevious(): ?float
    {
        return self::percent($this->ratioToPrevious());
    }

    public function percentToBase(): ?float
    {
        return self::percent($this->ratioToBase());
    }

    public static function percent(?float $ratio): ?float
    {
        return null === $ratio ? null : round($ratio * 100, self::PERCENT_PRECISION);
    }
}
