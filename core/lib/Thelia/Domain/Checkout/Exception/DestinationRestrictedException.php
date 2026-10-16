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

namespace Thelia\Domain\Checkout\Exception;

use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Shipping\DestinationRestriction\RestrictedLine;

/**
 * Products of the cart cannot be shipped to the chosen address: a destination
 * restriction of their group refuses it. Only products of the buyer's own cart are
 * named, with the reason of their group, never the destinations it lists.
 */
class DestinationRestrictedException extends InvalidDeliveryException
{
    /**
     * @param list<RestrictedLine> $lines
     */
    public function __construct(
        public readonly array $lines,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        CheckoutException::__construct(
            \count($lines) > 1
                ? '%products cannot be shipped to this address. Remove them from your cart or choose another address.'
                : '%products cannot be shipped to this address. Remove it from your cart or choose another address.',
            $code,
            $previous,
            ['%products' => implode(', ', array_map(static fn (RestrictedLine $line): string => $line->label(), $lines))],
        );
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::DeliveryDestinationRestricted->value;
    }

    public function violationDetails(): array
    {
        return ['productIds' => array_map(static fn (RestrictedLine $line): int => $line->productId, $this->lines)];
    }
}
