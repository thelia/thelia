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
 * A day of the window a carrier offers, open or not.
 *
 * Every day between the first and the last one offered is listed, closed days included, so
 * that a calendar can be drawn from the list alone without computing a date in the browser.
 * A day is open when the carrier delivers on it at all, and available when it is open and,
 * for a carrier offering slots, one of its slots can still be taken: a day can be open and
 * full.
 */
final readonly class DeliveryDay
{
    /**
     * @param list<DeliverySlotOffer> $slots the slots of the day, empty when the carrier offers a free date or the day is closed
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public bool $open,
        public bool $available,
        public array $slots = [],
    ) {
    }

    public function slot(int $slotId): ?DeliverySlotOffer
    {
        foreach ($this->slots as $slot) {
            if ($slot->id === $slotId) {
                return $slot;
            }
        }

        return null;
    }
}
