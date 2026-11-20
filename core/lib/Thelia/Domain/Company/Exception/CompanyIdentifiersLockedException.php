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
 * The SIRET and the VAT number of a company stay as they are once an order of the company was left
 * without VAT: the exemption was granted on them.
 */
final class CompanyIdentifiersLockedException extends CompanyException
{
    public static function forCompany(int $companyId): self
    {
        return new self(\sprintf('The identifiers of the company %d cannot be changed: orders were left without VAT on them.', $companyId));
    }
}
