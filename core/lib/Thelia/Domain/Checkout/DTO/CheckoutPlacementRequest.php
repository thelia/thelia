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

namespace Thelia\Domain\Checkout\DTO;

use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;

/**
 * Everything the placement of an order needs, and nothing it used to go and read off a
 * session.
 *
 * The four things a session used to answer are asked for here: who is buying, in what
 * currency, in what language, and what for. The rest — the addresses, the carrier, the
 * payment module — is on the cart, written there by the selection services the buyer
 * goes through, and is deliberately not repeated: the cart is what the order is built
 * from, and a second statement of the same choice is a second chance to disagree with it.
 *
 * The consents are the exception, and the reason is the same one that puts the rest on
 * the cart. An answer to a box has nowhere to be written down before the order exists —
 * the shop deliberately keeps no record of what a cart abandoned at the payment step
 * agreed to — so a caller with no session states it in the one request that has an order
 * to write it on.
 */
final readonly class CheckoutPlacementRequest
{
    /**
     * @param array<string, bool> $consentAnswers what the buyer answered to each consent, by consent code.
     *                                            Left empty by a caller that has a session holding them
     *                                            already, which is how the tunnel of a theme works.
     */
    public function __construct(
        public Cart $cart,
        public Customer $customer,
        public Currency $currency,
        public Lang $lang,
        public array $consentAnswers = [],
    ) {
    }
}
