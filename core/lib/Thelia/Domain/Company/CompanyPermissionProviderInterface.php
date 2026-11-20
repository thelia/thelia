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
 * Declares the rights a module adds to the ones of {@see CompanyPermission}.
 *
 * Implementations are auto-registered: any service implementing this interface is tagged
 * `thelia.company.permission_provider` and read by {@see Service\CompanyPermissionRegistry}, which
 * lists them beside the core ones wherever a role is edited. The module then asks
 * {@see Service\CompanyPermissionChecker::can()} about its own code.
 */
interface CompanyPermissionProviderInterface
{
    /**
     * The codes this module adds, each with the label shown where a role is edited. A code that
     * the core already defines is ignored.
     *
     * @return array<string, string> code => label
     */
    public function permissions(): array;
}
