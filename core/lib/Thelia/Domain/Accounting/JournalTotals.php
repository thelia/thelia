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
 * What the pieces of a period add up to, in cents of the shop currency.
 */
final readonly class JournalTotals
{
    /**
     * @param int|null $creditNotes how many of the pieces are credit notes, null while the credit notes are not in the journal
     */
    public function __construct(
        public int $pieces,
        public ?int $creditNotes,
        public int $debitCents,
        public int $creditCents,
        public int $taxableCents,
        public int $taxCents,
    ) {
    }
}
