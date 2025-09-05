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

namespace Thelia\Domain\Checkout\Enum;

enum DeliveryMode: string
{
    case LOCAL_PICKUP = 'localPickup';
    case DELIVERY = 'delivery';
    case PICKUP = 'pickup';

    public static function fromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return match ($value) {
            'localPickup' => self::LOCAL_PICKUP,
            'delivery' => self::DELIVERY,
            'pickup' => self::PICKUP,
            default => null,
        };
    }
}
