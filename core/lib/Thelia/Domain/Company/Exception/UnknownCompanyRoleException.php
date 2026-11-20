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
 * The role is not one this company may give: it does not exist, or belongs to another company.
 */
final class UnknownCompanyRoleException extends CompanyException
{
    public static function forCompany(string $roleCode, int $companyId): self
    {
        return new self(\sprintf('The company %2$d has no role "%1$s".', $roleCode, $companyId));
    }
}
