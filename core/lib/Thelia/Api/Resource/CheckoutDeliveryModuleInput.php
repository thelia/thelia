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
 * Only the module is taken. The option a carrier offers — a pick-up point, a delivery
 * slot — has no place to go: the checkout carries no such choice, and the front API will
 * take one the day the domain has somewhere to put it.
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
