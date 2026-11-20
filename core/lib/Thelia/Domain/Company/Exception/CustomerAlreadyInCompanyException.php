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

final class CustomerAlreadyInCompanyException extends CompanyException
{
    public static function withId(int $customerId): self
    {
        return new self(\sprintf('The customer %d already belongs to a company: an account belongs to one company at most.', $customerId));
    }
}
