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

use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementResult;
use Thelia\Domain\Checkout\DTO\ExpressCheckoutRequest;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\Cart;
use Thelia\Model\Country;
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
    ) {
    }

    /**
     * @throws ExpressCheckoutRefusedException when the shop and the wallet do not agree
     */
    public function place(ExpressCheckoutRequest $request): CheckoutPlacementResult
    {
        $cart = $request->cart;

        $customer = $this->customerOf($cart);
        $destination = $this->destinationTheCheckoutChose($cart);

        $this->writeThePaymentModuleOnTheCart($cart, $request);
        $this->refuseATotalTheBuyerNeverSaw($cart, $destination, $request);

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
     * buyer chose.
     */
    private function destinationTheCheckoutChose(Cart $cart): Country
    {
        $country = $cart->getCartAddressRelatedByAddressDeliveryId()?->getCountry();

        if (null === $cart->getDeliveryModuleId() || null === $cart->getAddressInvoiceId() || null === $country) {
            throw ExpressCheckoutRefusedException::becauseNoDeliveryIsChosenYet();
        }

        return $country;
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
    private function refuseATotalTheBuyerNeverSaw(Cart $cart, Country $destination, ExpressCheckoutRequest $request): void
    {
        $computed = round((float) $cart->getTaxedAmount($destination) + (float) $cart->getPostage(), 2);
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
