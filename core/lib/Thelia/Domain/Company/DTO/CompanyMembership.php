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

namespace Thelia\Domain\Company\DTO;

/**
 * What a customer is within a company: which one, under which role, with what limits.
 *
 * The limits are DECIMAL strings in the default currency, null for no limit.
 */
final readonly class CompanyMembership
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public int $companyId,
        public int $customerId,
        public string $roleCode,
        public array $permissions,
        public bool $companyActive,
        public bool $companyHasBillingAddress,
        public ?string $orderLimit,
        public ?string $periodLimit,
        public ?string $period,
    ) {
    }

    public function has(string $permission): bool
    {
        return \in_array($permission, $this->permissions, true);
    }
}
