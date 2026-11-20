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
 * What an invitation to join a company says, once its signature and its date are checked.
 */
final readonly class CompanyInvitation
{
    public function __construct(
        public int $companyId,
        public string $email,
        public string $roleCode,
        public ?string $orderLimit,
        public ?string $periodLimit,
        public ?string $period,
        public int $expiresAt,
        public ?int $issuerCustomerId,
    ) {
    }
}
