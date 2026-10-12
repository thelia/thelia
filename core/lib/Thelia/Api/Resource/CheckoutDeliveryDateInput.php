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

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * The delivery day, and the slot of that day, the buyer picked for the carrier of the cart.
 *
 * A null day clears the choice. Whatever is posted is judged against what the carrier offers
 * at that moment: a day outside the window, on a closed day, written any other way than
 * YYYY-MM-DD, or a full slot is refused, always in the shape GET .../validation answers in.
 */
final class CheckoutDeliveryDateInput
{
    #[ApiProperty(
        description: 'The day as Y-m-d, among those GET /front/delivery_modules/{id}/delivery_dates lists as available. Null clears the choice.',
        example: '2026-10-09',
    )]
    #[Groups([Checkout::GROUP_FRONT_WRITE])]
    public ?string $deliveryDate = null;

    #[ApiProperty(
        description: 'The slot of that day, required when the carrier offers slots, absent otherwise.',
        example: 4,
    )]
    #[Groups([Checkout::GROUP_FRONT_WRITE])]
    public ?int $deliverySlotId = null;
}
