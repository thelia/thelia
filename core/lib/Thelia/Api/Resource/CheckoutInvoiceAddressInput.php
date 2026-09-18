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
 * Who the order is billed to: one address of the account, named by its id.
 */
final class CheckoutInvoiceAddressInput
{
    #[ApiProperty(
        description: 'Identifier of an address of the authenticated account, billed to.',
        required: true,
        example: 12,
    )]
    #[NotNull]
    #[Positive]
    #[Groups([Checkout::GROUP_FRONT_WRITE])]
    public ?int $addressId = null;
}
