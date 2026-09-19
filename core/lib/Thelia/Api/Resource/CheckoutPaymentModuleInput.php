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
 * How the order is paid, named by the module id `GET /front/payment/modules` reports.
 */
final class CheckoutPaymentModuleInput
{
    #[ApiProperty(
        description: 'Identifier of an activated payment module, as listed by GET /front/payment/modules.',
        required: true,
        example: 4,
    )]
    #[NotNull]
    #[Positive]
    #[Groups([Checkout::GROUP_FRONT_WRITE])]
    public ?int $paymentModuleId = null;
}
