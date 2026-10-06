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
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * Who carries the order, named by the module id `GET /front/delivery_modules` reports.
 *
 * Only the module is taken. A pick-up point has no place to go yet; the delivery day and
 * slot are posted apart, to .../delivery_date, once the carrier is chosen.
 */
final class CheckoutDeliveryModuleInput
{
    #[ApiProperty(
        description: 'Identifier of an activated delivery module, as listed by GET /front/delivery_modules.',
        required: true,
        example: 3,
    )]
    #[NotNull]
    #[Positive]
    #[Groups([Checkout::GROUP_FRONT_WRITE])]
    public ?int $deliveryModuleId = null;
}
