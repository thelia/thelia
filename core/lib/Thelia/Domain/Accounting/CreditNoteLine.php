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
 * A line of a credit note: unit amounts and a quantity. For a product of the order, the module
 * copies the unit price and tax frozen on that order: its amounts give the rate of the order.
 */
final readonly class CreditNoteLine
{
    public const SHIPPING = 'shipping';

    public function __construct(
        public string $type,
        public float $unitPrice,
        public float $unitPriceWithTax,
        public int $quantity,
    ) {
    }

    public function isShipping(): bool
    {
        return self::SHIPPING === $this->type;
    }
}
