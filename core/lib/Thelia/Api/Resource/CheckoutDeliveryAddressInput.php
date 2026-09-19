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
 * Where the order is shipped to: one address of the account, named by its id.
 *
 * Nothing else is taken. The postage is quoted by the shop from the address the cart
 * ends up with, so an amount stated here would be a price the buyer chose.
 */
final class CheckoutDeliveryAddressInput
{
    #[ApiProperty(
        description: 'Identifier of an address of the authenticated account, shipped to.',
        required: true,
        example: 12,
    )]
    #[NotNull]
    #[Positive]
    #[Groups([Checkout::GROUP_FRONT_WRITE])]
    public ?int $addressId = null;
}
