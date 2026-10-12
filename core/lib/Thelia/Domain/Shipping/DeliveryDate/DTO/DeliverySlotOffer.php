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

/**
 * A slot of a day as the buyer sees it: its hours and whether it can still be taken.
 *
 * Whether, never how many: the number of orders a slot already holds is the merchant's
 * business, and a buyer who could read it would also read how busy the shop is.
 */
final readonly class DeliverySlotOffer
{
    public function __construct(
        public int $id,
        public ?string $title,
        public string $startTime,
        public string $endTime,
        public bool $available,
    ) {
    }
}
