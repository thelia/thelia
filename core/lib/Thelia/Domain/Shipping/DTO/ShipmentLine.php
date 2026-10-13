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
 * One line asked into a shipment: which order line, and how much of it.
 */
final readonly class ShipmentLine
{
    public function __construct(
        public int $orderProductId,
        public float $quantity,
    ) {
    }
}
