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

namespace Thelia\Domain\Cart\Discount;

/**
 * A named amount a module takes off a cart, beside the promotions: a gift card, a
 * store credit, a loyalty reward.
 *
 * The module states the amount tax included, the way the cart discount is stored;
 * the core works out its untaxed part the way it does for the promotions.
 */
final readonly class CartDiscountLine
{
    /**
     * @param string $key         what identifies the line for good, prefixed with the module code
     *                            (`CreditAccount.balance`): the order keeps it beside the amount
     * @param string $label       what the buyer reads, already translated
     * @param float  $taxedAmount what the line takes off, tax included, above zero
     */
    public function __construct(
        public string $key,
        public string $label,
        public float $taxedAmount,
    ) {
        if ('' === trim($key) || \strlen($key) > 64) {
            throw new \InvalidArgumentException('A discount line needs a key of 1 to 64 characters.');
        }

        if ('' === trim($label)) {
            throw new \InvalidArgumentException(\sprintf('The discount line "%s" needs a label.', $key));
        }

        if (!is_finite($taxedAmount) || $taxedAmount < 0.0) {
            throw new \InvalidArgumentException(\sprintf('The discount line "%s" cannot take off a negative amount.', $key));
        }
    }
}
