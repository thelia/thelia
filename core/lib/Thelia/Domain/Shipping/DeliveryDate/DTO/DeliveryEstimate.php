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

namespace Thelia\Domain\Shipping\DeliveryDate\DTO;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The days a parcel is expected between, both included, as calendar days of the shop.
 */
#[Exclude]
final readonly class DeliveryEstimate
{
    public const string DAY_FORMAT = 'Y-m-d';

    public function __construct(
        public \DateTimeImmutable $earliest,
        public \DateTimeImmutable $latest,
    ) {
    }

    public function earliestDay(): string
    {
        return $this->earliest->format(self::DAY_FORMAT);
    }

    public function latestDay(): string
    {
        return $this->latest->format(self::DAY_FORMAT);
    }

    public function isSoonerThan(self $other): bool
    {
        return [$this->earliest, $this->latest] < [$other->earliest, $other->latest];
    }
}
