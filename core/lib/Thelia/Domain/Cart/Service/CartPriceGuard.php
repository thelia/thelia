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

namespace Thelia\Domain\Cart\Service;

use Thelia\Core\Security\User\UserInterface;
use Thelia\Domain\Cart\Exception\CartPricesChangedException;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * Keeps an order from being written on prices the buyer was not shown.
 *
 * The price of a line is the proof of what the shop agreed to, written when the line is
 * added. A customer group can move it afterwards (the customer is put in another group, a
 * group price is edited) and everything an order is made of reads the lines: the coupon, the
 * postage thresholds, the fingerprint, the parts of a split payment. So the lines are
 * settled first, at the start of the payment, and the placement stops when one moved: the
 * buyer sees the new prices, and what they confirm next is calculated on them. The discount of
 * the promotions and the postage are priced again with the lines, so that the cart read after the
 * refusal, whatever the way it is read, tells the same story.
 *
 * Only a shop where a customer group prices something is concerned: elsewhere the lines are
 * kept as written, as they always were, and no pricing is run.
 */
final readonly class CartPriceGuard
{
    public function __construct(
        private CartLinePricer $cartLinePricer,
        private CatalogActivityProbe $catalogActivityProbe,
        private CartDiscountReconciler $cartDiscountReconciler,
    ) {
    }

    /**
     * The customer the cart is priced for: the one the cart names, read back from the
     * database, never the copy a session keeps from sign-in, and the session customer (a
     * guest checking out) when the cart names none.
     */
    public function buyerOf(Cart $cart, ?UserInterface $sessionCustomer): ?Customer
    {
        $buyer = null === $cart->getCustomerId() ? null : $cart->getCustomer();

        return $buyer ?? ($sessionCustomer instanceof Customer ? $sessionCustomer : null);
    }

    /**
     * Prices the lines again, saves the ones that moved and refuses when there is one.
     *
     * @throws CartPricesChangedException
     */
    public function settleOrRefuse(Cart $cart, Currency $currency, ?Customer $buyer): void
    {
        if (!$this->concernsThisShop()) {
            return;
        }

        // Read on the lines the discount was written for, before they move.
        $discountIsThePromotionsOwn = $this->cartDiscountReconciler->discountIsThePromotionsOwn($cart, $buyer);

        $moved = $this->cartLinePricer->reprice($cart, $currency, $buyer);

        if ([] !== $moved) {
            // The discount and the postage follow the lines now: the buyer who sees the cart again,
            // and the call that follows, read what they will pay and not the old total.
            $this->cartDiscountReconciler->reconcile($cart, $buyer, $discountIsThePromotionsOwn);

            throw new CartPricesChangedException($this->idsOf($moved));
        }
    }

    /**
     * Refuses when a line is not at the price it is owed, writing nothing.
     *
     * @throws CartPricesChangedException
     */
    public function refuseIfStale(Cart $cart, Currency $currency, ?Customer $buyer): void
    {
        if (!$this->concernsThisShop()) {
            return;
        }

        $stale = $this->cartLinePricer->staleLinesOf($cart, $currency, $buyer);

        if ([] !== $stale) {
            throw new CartPricesChangedException($this->idsOf($stale));
        }
    }

    private function concernsThisShop(): bool
    {
        return $this->catalogActivityProbe->read()->customerGroupsPriceAnything();
    }

    /**
     * @param list<CartItem> $lines
     *
     * @return list<int>
     */
    private function idsOf(array $lines): array
    {
        return array_map(static fn (CartItem $line): int => (int) $line->getId(), $lines);
    }
}
