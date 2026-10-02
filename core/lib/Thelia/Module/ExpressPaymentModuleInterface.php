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

use Symfony\Component\HttpFoundation\Request;
use Thelia\Domain\Checkout\DTO\ExpressPaymentButton;
use Thelia\Domain\Checkout\DTO\ExpressWalletAnswer;
use Thelia\Domain\Checkout\Enum\ExpressPaymentZone;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Model\Cart;

/**
 * A payment module that takes the money of the checkout from a wallet the buyer already
 * carries.
 *
 * This contract is deliberately separate from {@see PaymentModuleInterface}: a module
 * implements both or only the first, and every module written before express payment
 * existed keeps working untouched. The collector asks `instanceof` and passes over the
 * rest.
 *
 * Thelia provides the place and the plumbing — the zone the button appears in, the
 * amount the sheet charges, the confirmation route and the order at the end. The module owns everything that faces the wallet: what the button looks like,
 * the token it validates, the conversation with the provider.
 *
 * The confirmation is posted to the route `express_checkout_confirm`, whose URL and
 * token come with every button the shop renders. The shop checks the token, reads the
 * module's answer, identifies the buyer and places the order: a module never writes a
 * confirmation route of its own, and never touches the session or the cart's owner.
 */
interface ExpressPaymentModuleInterface
{
    /**
     * The zones this module can fill, whatever the shop has turned on.
     *
     * The shop shows a button only where the merchant turned the zone on and the module
     * listed it here.
     *
     * @return list<ExpressPaymentZone>
     */
    public function expressPaymentZones(): array;

    /**
     * Whether the checkout may also place an order with this module itself, as it does with
     * any other payment method.
     *
     * A wallet answers false: it is listed among the payment methods, choosing it turns the
     * order button into its own button, and only its confirmation places the order. A module
     * that also takes a card through the ordinary checkout answers true.
     */
    public function isAlsoOfferedAtCheckout(): bool;

    /**
     * The button to show for this cart, or null to show nothing.
     *
     * Answer null rather than an unusable button whenever this cart is not one the module
     * can take money for — a currency it does not support, an amount outside its range.
     * This is called on every render of the checkout's payment step, so it must not call
     * the provider: it decides from what it already knows.
     */
    public function expressPaymentButton(Cart $cart, ExpressPaymentZone $zone): ?ExpressPaymentButton;

    /**
     * What the wallet handed back, once the module has checked it with its provider.
     *
     * The request is the one the front posted to the shop's confirmation route, body and
     * headers as the module's own script sent them. The cart is the one in the buyer's
     * session, already matched against the token of the button that was clicked: it is
     * there to be compared with what the provider says was paid for, not to be written on.
     *
     * Throw a refusal when the provider does not vouch for the payment. Nothing is placed
     * then, and the buyer is left with their cart.
     *
     * @throws ExpressCheckoutRefusedException
     */
    public function readExpressPaymentConfirmation(Request $request, Cart $cart): ExpressWalletAnswer;
}
