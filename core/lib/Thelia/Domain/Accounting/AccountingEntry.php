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

namespace Thelia\Domain\Accounting;

/**
 * One line of a piece, in whole cents of the shop currency. Exactly one of the debit and
 * the credit is non-zero.
 */
final readonly class AccountingEntry
{
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_PRODUCT = 'product';
    public const ROLE_SHIPPING = 'shipping';
    public const ROLE_TAX = 'tax';

    public function __construct(
        public string $role,
        public string $account,
        public ?string $rateKey,
        public int $debitCents,
        public int $creditCents,
        public ?int $foreignCents = null,
    ) {
    }
}
