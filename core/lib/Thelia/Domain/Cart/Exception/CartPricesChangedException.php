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

namespace Thelia\Domain\Cart\Exception;

use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Exception\CheckoutException;

/**
 * The prices of the cart are not the ones the buyer was shown.
 *
 * Raised at the start of the payment when the customer price context (the group of the
 * customer, a price rule, a reserved operation) no longer gives a line the price it carries.
 * The lines are priced again, and the discount and the postage with them, before that refusal:
 * the buyer reads their cart at the right prices, and the coupon, the postage and the
 * fingerprint of the order they confirm are calculated on those.
 *
 * Raised by the order factory too, as a last line of defence, when a line still does not
 * match as the order is written. That one writes nothing: the lines, the discount and the
 * postage are left as they were, and the next payment attempt settles them (and refuses once
 * more if a price moved).
 */
final class CartPricesChangedException extends CheckoutException
{
    /**
     * @param list<int> $cartItemIds the lines whose price moved
     */
    public function __construct(public readonly array $cartItemIds = [], ?\Throwable $previous = null)
    {
        parent::__construct('The prices of your cart have changed. Please review your cart before paying.', 0, $previous);
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::CartPricesChanged->value;
    }

    public function violationDetails(): array
    {
        return ['cartItemIds' => $this->cartItemIds];
    }
}
