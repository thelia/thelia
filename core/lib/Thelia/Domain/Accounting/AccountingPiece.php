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
 * The entries of one invoice: the piece of the sales journal, under the reference and the
 * date of the invoice.
 */
final readonly class AccountingPiece
{
    /**
     * @param list<AccountingEntry> $entries the customer first, then the products, the shipping and the taxes
     */
    public function __construct(
        public int $orderId,
        public string $invoiceRef,
        public \DateTimeInterface $invoiceDate,
        public string $customerRef,
        public string $customerName,
        public ?string $foreignCurrencyCode,
        public array $entries,
    ) {
    }
}
