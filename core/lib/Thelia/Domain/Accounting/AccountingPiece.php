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
 * The entries of one invoice or one credit note: the piece of the sales journal, under its
 * reference and its date of invoice.
 */
final readonly class AccountingPiece
{
    public const INVOICE = 'invoice';
    public const CREDIT_NOTE = 'credit_note';

    /**
     * @param self::INVOICE|self::CREDIT_NOTE $kind
     * @param int                             $sourceId the order of an invoice, the credit note of the CreditNote module
     * @param list<AccountingEntry>           $entries  the customer first, then the products, the shipping and the taxes
     * @param list<string>                    $notes    what the accountant should check on this invoice, for the report
     */
    public function __construct(
        public string $kind,
        public int $sourceId,
        public string $invoiceRef,
        public \DateTimeInterface $invoiceDate,
        public string $customerRef,
        public string $customerName,
        public ?string $foreignCurrencyCode,
        public array $entries,
        public array $notes = [],
    ) {
    }

    public function isCreditNote(): bool
    {
        return self::CREDIT_NOTE === $this->kind;
    }
}
