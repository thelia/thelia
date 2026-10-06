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

use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;

/**
 * The days, and the slots of each day, a carrier offers from a given day on.
 *
 * Never None: a carrier that offers no date has no offer at all.
 */
final readonly class DeliveryDateOffer
{
    /**
     * @param list<DeliveryDay> $days every day from the first one offered to the last one, in order
     */
    public function __construct(
        public int $moduleId,
        public DeliveryDateChoiceMode $choiceMode,
        public array $days,
    ) {
    }

    public function day(\DateTimeInterface $date): ?DeliveryDay
    {
        $wanted = $date->format('Y-m-d');

        foreach ($this->days as $day) {
            if ($day->date->format('Y-m-d') === $wanted) {
                return $day;
            }
        }

        return null;
    }

    public function hasAvailableDay(): bool
    {
        foreach ($this->days as $day) {
            if ($day->available) {
                return true;
            }
        }

        return false;
    }
}
