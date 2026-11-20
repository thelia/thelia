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

namespace Thelia\Domain\Company\Exception;

/**
 * The address cannot be shared with, or taken back from, the company of the customer who asks.
 */
final class AddressSharingRefusedException extends CompanyException
{
    public static function notAMember(int $customerId): self
    {
        return new self(\sprintf('The customer %d belongs to no company: there is nobody to share an address with.', $customerId));
    }

    public static function notTheCreator(int $customerId, int $addressId): self
    {
        return new self(\sprintf('The customer %d did not create the address %d: only its creator shares it with a company.', $customerId, $addressId));
    }

    public static function notAllowedToChange(int $customerId, int $addressId): self
    {
        return new self(\sprintf('The customer %d may not stop the address %d from being shared.', $customerId, $addressId));
    }

    public static function isTheBillingAddress(int $addressId): self
    {
        return new self(\sprintf('The address %d is the billing address of its company: choose another one before it stops being shared.', $addressId));
    }
}
