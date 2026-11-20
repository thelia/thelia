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
use Thelia\Model\CompanyCustomer;

/**
 * A buyer was given another role, and the change is written.
 */
final class CompanyCustomerRoleChangedEvent extends Event
{
    public function __construct(
        private readonly CompanyCustomer $buyer,
        private readonly string $previousRoleCode,
        private readonly string $roleCode,
    ) {
    }

    public function getBuyer(): CompanyCustomer
    {
        return $this->buyer;
    }

    public function getPreviousRoleCode(): string
    {
        return $this->previousRoleCode;
    }

    public function getRoleCode(): string
    {
        return $this->roleCode;
    }
}
