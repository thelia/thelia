<?php

namespace Thelia\Core\Enum;

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
