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

namespace Thelia\Domain\Payment\DTO;

/**
 * One part of an order payment, before anything is written.
 */
final readonly class PlannedPaymentPart
{
    public function __construct(
        public int $moduleId,
        public string $amount,   // decimal string, 6 decimals
        public bool $isPrimary,
        public int $position,    // 0, 1, 2… in settlement order
        public bool $isInstant,
    ) {
    }
}
