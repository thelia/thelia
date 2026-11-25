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

namespace Thelia\Domain\Quote\Acceptance;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\QuoteItemTableMap;
use Thelia\Model\QuoteItem;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevision;

/**
 * The lines of a revision, and whether a cart still holds exactly them.
 */
final readonly class QuoteCartLines
{
    /**
     * @return list<QuoteItem>
     */
    public function linesOf(QuoteRevision $revision, ConnectionInterface $connection): array
    {
        QuoteItemTableMap::clearInstancePool();

        return [...QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find($connection)];
    }

    /**
     * Whether the cart still is the revision: its discount, and exactly one line per line of the
     * revision with the sale element, the quantity and the price it was written with.
     */
    public function holds(Cart $cart, QuoteRevision $revision, ConnectionInterface $connection): bool
    {
        if (round((float) $cart->getDiscount(), 6) !== round((float) $revision->getDiscount(), 6)) {
            return false;
        }

        CartItemTableMap::clearInstancePool();

        $cartLines = [];

        foreach (CartItemQuery::create()->filterByCartId($cart->getId())->find($connection) as $cartLine) {
            if (null === $cartLine->getQuoteItemId()) {
                return false;
            }

            $cartLines[(int) $cartLine->getQuoteItemId()] = $cartLine;
        }

        $quoteLines = $this->linesOf($revision, $connection);

        if (\count($cartLines) !== \count($quoteLines)) {
            return false;
        }

        foreach ($quoteLines as $quoteLine) {
            $cartLine = $cartLines[(int) $quoteLine->getId()] ?? null;

            if (null === $cartLine
                || (int) $cartLine->getProductSaleElementsId() !== (int) $quoteLine->getProductSaleElementsId()
                || (float) $cartLine->getQuantity() !== (float) $quoteLine->getQuantity()
                || round((float) $cartLine->getPrice(), 6) !== round((float) $quoteLine->getPrice(), 6)) {
                return false;
            }
        }

        return true;
    }
}
