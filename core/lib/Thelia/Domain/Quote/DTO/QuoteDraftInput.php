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

namespace Thelia\Domain\Quote\DTO;

/**
 * A whole revision as the shop writes it: every call replaces what the draft held.
 *
 * The discount is an amount with taxes, taken off the total with taxes; the postage is an amount
 * without taxes; both are decimal strings.
 */
final readonly class QuoteDraftInput
{
    /**
     * @param list<QuoteDraftLine> $lines
     */
    public function __construct(
        public array $lines,
        public string $discount = '0',
        public string $postage = '0',
        public ?int $deliveryModuleId = null,
        public ?string $merchantNote = null,
        public ?\DateTimeImmutable $validUntil = null,
    ) {
    }
}
