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
 * The change would leave the company with no administrator able to run it, or with nobody to
 * take the shared addresses a leaving buyer created.
 */
final class LastCompanyAdministratorException extends CompanyException
{
    public static function leaving(int $companyId): self
    {
        return new self(\sprintf('The company %d would be left without an administrator: give the role to another buyer first.', $companyId));
    }

    public static function noOneToTakeTheAddresses(int $companyId): self
    {
        return new self(\sprintf('The company %d has no administrator to take the addresses shared by the buyer who leaves.', $companyId));
    }
}
