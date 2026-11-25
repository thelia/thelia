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
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderQuery;

/**
 * A quote gives one order at most (the order names it, once), and an order that was cancelled is
 * no order: the buyer whose payment failed, or who chose another way to pay, places the order again.
 * The cancelled order lets go of the quote first, so that the one that follows can name it.
 */
final readonly class QuoteOrderSlot
{
    public function releaseFromCancelledOrders(Cart $cart): void
    {
        $quoteId = $cart->getQuoteId();

        if (null === $quoteId) {
            return;
        }

        $cancelledIds = [];

        foreach (OrderQuery::create()->filterByQuoteId($quoteId)->find() as $order) {
            if ($order->isCancelled()) {
                $cancelledIds[] = $order->getId();
            }
        }

        if ([] === $cancelledIds) {
            return;
        }

        OrderQuery::create()->filterById($cancelledIds)->update(['QuoteId' => null]);
        OrderTableMap::clearInstancePool();
    }
}
