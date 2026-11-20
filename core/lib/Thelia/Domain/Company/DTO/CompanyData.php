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
 * The details of a company a writer states in full: what is not given is emptied.
 */
final readonly class CompanyData
{
    public function __construct(
        public string $name,
        public ?string $siret = null,
        public ?string $vatNumber = null,
        public ?int $parentId = null,
        public ?string $notificationEmail = null,
        public ?string $internalNote = null,
        public ?int $billingAddressId = null,
    ) {
    }
}
