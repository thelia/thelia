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

namespace Thelia\Domain\Company;

/**
 * The codes of the roles every company may give, which the shop installs with no company of their own.
 */
final class CompanyRoleCode
{
    public const ADMINISTRATOR = 'company_admin';

    public const BUYER = 'buyer';

    public const APPROVER = 'approver';
}
