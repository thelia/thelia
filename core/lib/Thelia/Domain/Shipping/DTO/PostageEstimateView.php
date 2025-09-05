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

namespace Thelia\Domain\Shipping\DTO;

/**
 * Projection simple des frais de port.
 */
final class PostageEstimateView
{
    public function __construct(
        public readonly ?float $amountHt,
        public readonly ?float $tax,
        public readonly ?float $totalTtc,
    ) {
    }
}
