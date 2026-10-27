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
use Thelia\Domain\Checkout\Exception\CheckoutPlacementInProgressException;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
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
        $writtenDeliveryAddress = $this->writeTheDeliveryAddressOfANothingToShipCart($cart);

        try {
            return $this->placementService->place(new CheckoutPlacementRequest(
                $cart,
                $customer,
                $this->currencyOf($cart),
                $this->langOf($customer),
                $request->consentAnswers,
            ));
        } catch (CheckoutPlacementInProgressException $inProgress) {
            // Another request is placing this cart and may be reading this address.
            throw $inProgress;
        } catch (\Throwable $refused) {
            $this->forgetTheDeliveryAddressWrittenForThisAttempt($cart, $writtenDeliveryAddress);

            throw $refused;
        }
    }

    /**
     * Where the parcel goes: the carrier and the addresses the checkout wrote on the cart.
     * All of them have to be there, or the order would leave without a destination the
     * buyer chose. A cart with nothing to ship is never asked for a delivery address: its
     * carrier, its invoice address and the address it is taxed at are enough.
     */
    private function refuseACartWithoutTheDeliveryTheCheckoutChose(Cart $cart): void
    {
        $country = $this->checkoutTotal->addressTheCartIsTaxedFor($cart)?->getCountry();

        if (null === $cart->getDeliveryModuleId() || null === $cart->getAddressInvoiceId() || null === $country) {
            throw ExpressCheckoutRefusedException::becauseNoDeliveryIsChosenYet();
        }
    }

    /**
     * An order is written with a delivery address, even when nothing is shipped, and that
     * address is where the order is taxed. The one written here is the address the wallet's
     * total was taxed at, so the order is placed at the amount the buyer agreed to, on a
     * copy of its own no other address of the cart shares.
     */
    private function writeTheDeliveryAddressOfANothingToShipCart(Cart $cart): ?CartAddress
    {
        if (null !== $cart->getAddressDeliveryId() || !$cart->isVirtual()) {
            return null;
        }

        $taxedFor = $this->checkoutTotal->addressTheCartIsTaxedFor($cart);
        $copy = $taxedFor instanceof Address ? (new CartAddress())->copyFrom($taxedFor) : $taxedFor?->copy();
        $event = (new CartCheckoutEvent($cart))->setCartAddress($copy);

        $this->eventDispatcher->dispatch($event, TheliaEvents::CART_SET_DELIVERY_ADDRESS_MANUAL);

        return $copy;
    }

    /**
     * A refused attempt leaves the cart as it found it. The copy was made from an address
     * as it stood at that attempt: kept on the cart, it would become the delivery address of
     * the next one, even after the buyer corrected it.
     */
    private function forgetTheDeliveryAddressWrittenForThisAttempt(Cart $cart, ?CartAddress $writtenDeliveryAddress): void
    {
        if (null === $writtenDeliveryAddress || $cart->getAddressDeliveryId() !== $writtenDeliveryAddress->getId()) {
            return;
        }

        $this->eventDispatcher->dispatch(new CartCheckoutEvent($cart), TheliaEvents::CART_SET_DELIVERY_ADDRESS_MANUAL);
        $writtenDeliveryAddress->delete();
    }

    /**
     * Only the payment module changes: the carrier stays the one the buyer chose, and the
     * postage is computed again for it, as the checkout would before placing the order.
     * The choice goes through the same event as in the checkout, so its listeners see a
     * wallet the way they see any other payment module.
     */
    private function writeThePaymentModuleOnTheCart(Cart $cart, ExpressCheckoutRequest $request): void
    {
        $event = (new CartCheckoutEvent($cart))->setPaymentModuleId($request->paymentModuleId);
        $this->eventDispatcher->dispatch($event, TheliaEvents::CART_SET_PAYMENT_MODULE);

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
     *
     * Both are compared in cents: a float tolerance of one cent lets some one-cent gaps
     * through, depending on how the two amounts round in binary.
     */
    private function refuseATotalTheBuyerNeverSaw(Cart $cart, ExpressCheckoutRequest $request): void
    {
        $computed = $this->checkoutTotal->of($cart) ?? throw ExpressCheckoutRefusedException::becauseNoDeliveryIsChosenYet();
        $shown = round($request->totalTaxIncludedShownToTheBuyer, 2);

        if ($this->inCents($computed) !== $this->inCents($shown)) {
            throw ExpressCheckoutRefusedException::becauseTheTotalDoesNotMatch($shown, $computed);
        }
    }

    private function inCents(float $amount): int
    {
        return (int) round($amount * 100);
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
