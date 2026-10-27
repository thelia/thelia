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
 * A line of the cart holds a product the buyer may no longer see: a customer group
 * restricted to some categories, a rule of a module. Refused at the checkout whatever way the
 * line got there (added before the buyer signed in, a restriction written since), so that no
 * order is placed for something the catalog hides from them.
 *
 * Names the products, so that the buyer knows which lines to take out.
 */
final class CartLineUnavailableException extends CheckoutException
{
    /**
     * @param array<int, string> $products    product id => title
     * @param list<int>          $cartItemIds the lines that hold them
     */
    public function __construct(
        public readonly array $products,
        public readonly array $cartItemIds = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            \count($products) > 1
                ? '%products are no longer available to you. Remove them from your cart to complete your order.'
                : '%products is no longer available to you. Remove it from your cart to complete your order.',
            0,
            $previous,
            ['%products' => implode(', ', $products)],
        );
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::CartLineUnavailable->value;
    }

    public function violationDetails(): array
    {
        return ['productIds' => array_keys($this->products), 'cartItemIds' => $this->cartItemIds];
    }
}
