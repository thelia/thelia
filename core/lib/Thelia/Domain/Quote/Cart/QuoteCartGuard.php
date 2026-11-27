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

namespace Thelia\Domain\Quote\Cart;

use Thelia\Domain\Checkout\Exception\QuoteRequiredException;
use Thelia\Domain\Quote\Enum\QuoteMode;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;

/**
 * The refusals the cart of a quote and the quote mode of the shop impose.
 */
final readonly class QuoteCartGuard
{
    public function __construct(private QuoteCartInspector $inspector)
    {
    }

    public function isQuoteCart(Cart $cart): bool
    {
        return $this->inspector->isQuoteCart($cart);
    }

    /**
     * @throws QuoteCartLockedException when the cart is the cart of a quote
     */
    public function refuseLineChange(Cart $cart): void
    {
        if ($this->inspector->isQuoteCart($cart)) {
            throw QuoteCartLockedException::linesAreFrozen();
        }
    }

    /**
     * @throws QuoteCartLockedException when the cart is the cart of a quote
     */
    public function refuseCoupon(Cart $cart): void
    {
        if ($this->inspector->isQuoteCart($cart)) {
            throw QuoteCartLockedException::couponIsRefused();
        }
    }

    /**
     * A shop that only sells through quotes places no ordinary cart: the cart of a quote still goes
     * through.
     *
     * @throws QuoteRequiredException
     */
    public function refuseAnOrdinaryCartWhenQuotesAreRequired(Cart $cart): void
    {
        if ($this->inspector->isQuoteCart($cart)) {
            return;
        }

        if (QuoteMode::Only === QuoteMode::fromConfig((string) ConfigQuery::read('quote_mode', QuoteMode::Disabled->value))) {
            throw new QuoteRequiredException();
        }
    }
}
