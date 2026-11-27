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

use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Quote;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionQuery;

/**
 * What tells the cart of a quote from an ordinary one: it names the quote it carries, and the
 * revision of that quote the buyer accepted. Its lines name the line of the quote whose price
 * they hold.
 *
 * A column read, nothing more: the answer is cheap enough to be asked on every cart change.
 */
final readonly class QuoteCartInspector
{
    public function isQuoteCart(Cart $cart): bool
    {
        return null !== $cart->getQuoteId();
    }

    public function isFrozenLine(CartItem $line): bool
    {
        return null !== $line->getQuoteItemId();
    }

    public function quoteOf(Cart $cart): ?Quote
    {
        $quoteId = $cart->getQuoteId();

        return null === $quoteId ? null : QuoteQuery::create()->findPk($quoteId);
    }

    /**
     * The revision the buyer accepted, whose postage and discount the cart carries.
     */
    public function acceptedRevisionOf(Cart $cart): ?QuoteRevision
    {
        $quoteId = $cart->getQuoteId();
        $number = $cart->getQuoteRevision();

        if (null === $quoteId || null === $number) {
            return null;
        }

        return QuoteRevisionQuery::create()->filterByQuoteId($quoteId)->filterByNumber($number)->findOne();
    }
}
