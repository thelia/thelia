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

namespace Thelia\Domain\Company\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * A buyer left a company and the link is gone; the shared addresses the buyer created went to
 * another administrator of the company.
 */
final class CompanyCustomerDetachedEvent extends Event
{
    /**
     * @param list<int> $transferredAddressIds the shared addresses handed over
     */
    public function __construct(
        private readonly int $companyId,
        private readonly int $customerId,
        private readonly string $roleCode,
        private readonly array $transferredAddressIds,
        private readonly ?int $transferredToCustomerId,
    ) {
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getRoleCode(): string
    {
        return $this->roleCode;
    }

    /**
     * @return list<int>
     */
    public function getTransferredAddressIds(): array
    {
        return $this->transferredAddressIds;
    }

    public function getTransferredToCustomerId(): ?int
    {
        return $this->transferredToCustomerId;
    }
}
