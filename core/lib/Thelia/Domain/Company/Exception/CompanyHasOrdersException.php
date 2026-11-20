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
 * A company that orders were placed for is kept, so that its history stays readable: it is suspended instead.
 */
final class CompanyHasOrdersException extends CompanyException
{
    public static function forCompany(int $companyId): self
    {
        return new self(\sprintf('The company %d has orders: suspend it instead of deleting it.', $companyId));
    }
}
