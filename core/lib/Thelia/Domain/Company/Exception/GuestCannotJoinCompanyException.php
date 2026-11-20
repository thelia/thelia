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
 * A guest holds no password and is reusable by e-mail: nothing proves who is behind it, so it never becomes a buyer.
 */
final class GuestCannotJoinCompanyException extends CompanyException
{
    public static function withId(int $customerId): self
    {
        return new self(\sprintf('The customer %d is a guest: only an account can join a company.', $customerId));
    }
}
