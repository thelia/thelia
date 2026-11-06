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

namespace Thelia\Domain\Catalog\Stock;

/**
 * What the active reservations hold on one sale element.
 */
final readonly class ReservationDetail
{
    public function __construct(
        public float $quantity,
        public int $cartCount,
        public \DateTimeImmutable $nextExpiry,
    ) {
    }
}
