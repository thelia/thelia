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

namespace Thelia\Module;

use Thelia\Model\Cart;
use Thelia\Model\OrderPaymentPart;

/**
 * Optional: a payment means that settles part of an order at once, inside the shop
 * (gift card, credit note, wallet). The module keeps the buyer's choice on its side,
 * bound to the cart; the core only asks how much it applied.
 */
interface InstantPaymentModuleInterface
{
    /**
     * Decimal string, 6 decimals or fewer, "0" when the module applied nothing to this cart.
     *
     * The amount applied, for as long as the order is not paid: settling the part does not
     * change it. The core compares it with what the unpaid order was placed with to decide
     * whether a new payment attempt may present the same order again; a module that answers
     * "0" once its part is settled makes that attempt look like another payment, cancels the
     * unpaid order, and places one without the part.
     *
     * A module that implements this interface does not listen to ORDER_PAY_GET_TOTAL for an
     * order with payment parts: the core answers it, with the balance left for the provider,
     * and a listener that took its own amount off the total would take it off twice. An order
     * placed before the module implemented it has no part of its own, and the core leaves its
     * total alone when it has no part at all: a module may keep, for a transition, a listener
     * limited to the orders it holds an old-style deduction for and that have no payment part.
     */
    public function getAmountAppliedToCart(Cart $cart): string;

    /**
     * Spend the part. Throws to refuse; the core then releases what it already settled.
     *
     * Called inside a transaction of the core, under its locks. A module that writes on the
     * connection of the core does it inside that transaction and never rolls it back, even
     * one it opened itself on top of it: Propel then marks the whole transaction as unable
     * to commit, and the settlement of every part of the order is rolled back. To refuse,
     * throw; the core rolls back.
     */
    public function settlePart(OrderPaymentPart $part): void;

    /**
     * Give the part back. Called at most once per settled part.
     *
     * Same rule as settlePart(): throw to say the part could not be given back, never roll
     * back a transaction on the connection of the core. A failure that is thrown leaves this
     * part settled and the other parts released; a rollback makes the release of the whole
     * order fail to commit, after this module may have given back what it holds.
     *
     * A refund of the order does not release its parts: a module that gives the amount back
     * by itself when the order is refunded leaves the part `settled`, and is asked to release
     * it all the same if the order is cancelled later (an order refunded, turned unpaid again
     * and paid anew is taken back and replaced, see CheckoutPaymentService). It must then
     * recognise a part it already gave back, by the id of the part, and give nothing twice.
     */
    public function releasePart(OrderPaymentPart $part): void;
}
