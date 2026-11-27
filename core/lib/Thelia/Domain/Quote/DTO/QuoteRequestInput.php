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
 * What a buyer adds to the cart when asking for a quote.
 *
 * An address left out is the default one of the buyer.
 */
final readonly class QuoteRequestInput
{
    public function __construct(
        public ?string $comment = null,
        public ?\DateTimeImmutable $neededBy = null,
        public ?int $invoiceAddressId = null,
        public ?int $deliveryAddressId = null,
    ) {
    }
}
