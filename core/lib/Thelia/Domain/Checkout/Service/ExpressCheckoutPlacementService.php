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

namespace Thelia\Domain\Checkout\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementResult;
use Thelia\Domain\Checkout\DTO\ExpressCheckoutRequest;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;

/**
 * Turns a wallet's payment into an order, through the same door as the checkout.
 *
 * The wallet only paid: the buyer was identified and chose the addresses and the carrier
 * in the checkout, and those are what the order is made of. So this service does not
 * create anything itself. It writes the wallet as the payment module of the cart, then
 * hands the cart to {@see CheckoutPlacementService}. Everything that made placement safe
 * stays in one place: the lock, the cart fingerprint, and the row-level refusal of a cart
 * that has already been ordered.
 *
 * The amount is never taken on trust: the sheet says what it showed, the shop recomputes,
 * and a difference cancels the payment rather than charging a buyer something they did
 * not agree to.
 */
final readonly class ExpressCheckoutPlacementService
{
    private const TOTAL_TOLERANCE = 0.01;

    public function __construct(
        private CheckoutPlacementService $placementService,
        private PostageHandler $postageHandler,
        private ExpressCheckoutTotal $checkoutTotal,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws ExpressCheckoutRefusedException when the shop and the wallet do not agree
     */
    public function place(ExpressCheckoutRequest $request): CheckoutPlacementResult
    {
        $cart = $request->cart;

        $customer = $this->customerOf($cart);
        $this->refuseACartWithoutTheDeliveryTheCheckoutChose($cart);

        $this->writeThePaymentModuleOnTheCart($cart, $request);
        $this->refuseATotalTheBuyerNeverSaw($cart, $request);
        $this->writeTheDeliveryAddressOfANothingToShipCart($cart);

        return $this->placementService->place(new CheckoutPlacementRequest(
            $cart,
            $customer,
            $this->currencyOf($cart),
            $this->langOf($customer),
            $request->consentAnswers,
        ));
    }

    /**
     * Where the parcel goes: the carrier and the addresses the checkout wrote on the cart.
     * All of them have to be there, or the order would leave without a destination the
     * buyer chose. A cart with nothing to ship is never asked for a delivery address: its
     * carrier and its invoice address are enough.
     */
    private function refuseACartWithoutTheDeliveryTheCheckoutChose(Cart $cart): void
    {
        $country = $this->checkoutTotal->addressTheCartIsTaxedFor($cart)?->getCountry();

        if (null === $cart->getDeliveryModuleId() || null === $cart->getAddressInvoiceId() || null === $country) {
            throw ExpressCheckoutRefusedException::becauseNoDeliveryIsChosenYet();
        }
    }

    /**
     * An order is written with a delivery address, even when nothing is shipped. The buyer
     * of a cart with nothing to ship was only asked where they are invoiced, so the order
     * is delivered there, on a copy of its own the invoice address does not share.
     */
    private function writeTheDeliveryAddressOfANothingToShipCart(Cart $cart): void
    {
        if (null !== $cart->getAddressDeliveryId() || !$cart->isVirtual()) {
            return;
        }

        $event = (new CartCheckoutEvent($cart))
            ->setCartAddress($cart->getCartAddressRelatedByAddressInvoiceId()?->copy());

        $this->eventDispatcher->dispatch($event, TheliaEvents::CART_SET_DELIVERY_ADDRESS_MANUAL);
    }

    /**
     * Only the payment module changes: the carrier stays the one the buyer chose, and the
     * postage is computed again for it, as the checkout would before placing the order.
     */
    private function writeThePaymentModuleOnTheCart(Cart $cart, ExpressCheckoutRequest $request): void
    {
        $cart->setPaymentModuleId($request->paymentModuleId)->save();

        $this->postageHandler->handlePostageOnCart($cart);
    }

    /**
     * The customer this cart already belongs to.
     *
     * Identifying the buyer is the checkout's job, not this service's, and the reason is
     * mechanical. A cart carrying a customer the session does not know is read as
     * belonging to somebody else: the next read of the session cart restores it from the
     * cookie, duplicates it and deletes the row, including the one this service would be
     * holding.
     */
    private function customerOf(Cart $cart): Customer
    {
        return $cart->getCustomer() ?? throw ExpressCheckoutRefusedException::becauseTheCartCarriesNoCustomer();
    }

    /**
     * What the shop computes, against what the sheet showed. The shop wins, and a
     * difference means nothing is created at all.
     */
    private function refuseATotalTheBuyerNeverSaw(Cart $cart, ExpressCheckoutRequest $request): void
    {
        $computed = $this->checkoutTotal->of($cart) ?? throw ExpressCheckoutRefusedException::becauseNoDeliveryIsChosenYet();
        $shown = round($request->totalTaxIncludedShownToTheBuyer, 2);

        if (abs($computed - $shown) > self::TOTAL_TOLERANCE) {
            throw ExpressCheckoutRefusedException::becauseTheTotalDoesNotMatch($shown, $computed);
        }
    }

    private function currencyOf(Cart $cart): Currency
    {
        return $cart->getCurrency() ?? Currency::getDefaultCurrency();
    }

    private function langOf(Customer $customer): Lang
    {
        return $customer->getLangModel() ?? Lang::getDefaultLanguage();
    }
}
