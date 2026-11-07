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
 * A numbered credit note of the CreditNote module, as the sales journal reads it from the
 * tables of the module.
 */
final readonly class CreditNoteRecord
{
    /**
     * The types of the module that give money back without taking a sale back: a rebate,
     * a discount, a refund of a difference.
     */
    public const COMMERCIAL_TYPES = ['rebate', 'discount', 'difference_refund'];

    /**
     * @param list<CreditNoteLine> $lines
     */
    public function __construct(
        public int $id,
        public string $invoiceRef,
        public \DateTimeImmutable $invoiceDate,
        public string $typeCode,
        public string $customerRef,
        public string $customerName,
        public int $currencyId,
        public ?string $currencyCode,
        public float $currencyRate,
        public float $totalWithTax,
        public float $discountWithTax,
        public array $lines,
    ) {
    }

    public function isCommercial(): bool
    {
        return \in_array($this->typeCode, self::COMMERCIAL_TYPES, true);
    }
}
