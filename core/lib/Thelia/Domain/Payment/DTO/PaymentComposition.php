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

namespace Thelia\Domain\Payment\DTO;

final readonly class PaymentComposition
{
    /** @param list<PlannedPaymentPart> $parts empty when no instant share applies */
    public function __construct(
        public array $parts,
        public string $balance, // what the provider charges, "0.000000" when instant parts cover all
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->parts;
    }
}
