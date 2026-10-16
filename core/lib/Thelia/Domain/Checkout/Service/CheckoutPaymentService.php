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

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Checkout\DTO\OrderPaymentOutcome;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Exception\GuestCheckoutNotAllowedException;
use Thelia\Domain\Module\Payment\PaymentCartContext;
use Thelia\Domain\Order\Exception\CartAlreadyOrderedException;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Order\Service\GuestOrderAccessLimiter;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Domain\Order\Service\OrderFingerprint;
use Thelia\Domain\Payment\DTO\PaymentComposition;
use Thelia\Domain\Payment\Exception\PaymentCompositionRefusedException;
use Thelia\Domain\Payment\Exception\StoredMethodPaymentRateLimitedException;
use Thelia\Domain\Payment\OrderPaymentComposer;
use Thelia\Domain\Payment\Service\OrderPaymentPartSettlement;
use Thelia\Domain\Payment\StoredPaymentMethodPayment;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;

readonly class CheckoutPaymentService
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private SecurityContext $securityContext,
        private GuestOrderAccessLimiter $guestOrderAccessLimiter,
        private GuestOrderAccessService $guestOrderAccessService,
        private GuestCheckoutPolicy $guestCheckoutPolicy,
        private OrderFacade $orderFacade,
        private OrderFingerprint $orderFingerprint,
        private PaymentCartContext $paymentCartContext,
        private StoredPaymentMethodPayment $storedPaymentMethodPayment,
        private OrderPaymentComposer $orderPaymentComposer,
        private OrderPaymentPartSettlement $orderPaymentPartSettlement,
    ) {
    }

    /**
     * @throws GuestCheckoutNotAllowedException        when the shop stopped allowing this cart to be ordered without an account
     * @throws StoredMethodPaymentRateLimitedException when the customer already paid with a stored method too often this hour
     * @throws \Exception
     */
    public function pay(
        Cart $cart,
        int $deliveryAddressId,
        int $invoiceAddressId,
        int $deliveryModuleId,
        int $paymentModuleId,
    ): ?Response {
        return $this->payAndReturnOutcome(new OrderPaymentRequest(
            $cart,
            $deliveryAddressId,
            $invoiceAddressId,
            $deliveryModuleId,
            $paymentModuleId,
            customerPaymentMethodId: $cart->getCustomerPaymentMethodId(),
        ))->paymentResponse;
    }

    /**
     * The same placement, with the order it produced handed back next to the answer of
     * the payment module.
     *
     * `pay()` only ever returned the response, because the caller it was written for read
     * the order back out of the session afterwards. A caller with no session has nowhere
     * to read it from, so it comes back here — and the two go down the same path, ORDER_PAY,
     * so there is no second way to place an order to keep in step with the first.
     *
     * @throws GuestCheckoutNotAllowedException        when the shop stopped allowing this cart to be ordered without an account
     * @throws StoredMethodPaymentRateLimitedException when the customer already paid with a stored method too often this hour
     * @throws PaymentCompositionRefusedException      when the instant payment means of the cart and the payment module chosen cannot make up one payment
     * @throws CartAlreadyOrderedException             when this very cart was turned into an order by another request
     * @throws TheliaProcessException                  when nothing answered ORDER_PAY with an order
     * @throws \Exception
     */
    public function payAndReturnOutcome(OrderPaymentRequest $request): OrderPaymentOutcome
    {
        $cart = $request->cart;

        $this->refuseAGuestTheShopNoLongerAllows($cart);

        // Read once, here, because the fingerprint says what the order is paid with, and
        // refused here, before the unpaid order of the cart is looked at, let alone cancelled:
        // a composition the core refuses leaves the buyer with the order they had.
        $composition = $this->orderPaymentComposer->composeForCart($cart, $request->paymentModuleId);

        $unpaidOrder = $this->orderFacade->findUnpaidOrderOf($cart);

        if ($unpaidOrder instanceof Order && $this->mayBePresentedAgain($unpaidOrder, $cart, $request, $composition)) {
            return $this->payAgain($unpaidOrder, $cart);
        }

        $this->refuseAStoredMethodPaymentOverTheAllowance($cart);

        if ($unpaidOrder instanceof Order) {
            // Through the status flow, not written to the row: the stock the previous
            // order took is given back and the status listeners run. Done before the new
            // placement, which then finds a cart with no order standing on it.
            $unpaidOrder->setCancelled($this->dispatcher);
        }

        $newOrder = (new Order())
            ->setDeliveryOrderAddressId($request->deliveryAddressId)
            ->setInvoiceOrderAddressId($request->invoiceAddressId)
            ->setPaymentModuleId($request->paymentModuleId)
            ->setDeliveryModuleId($request->deliveryModuleId)
            ->setPostage((string) $cart->getPostage())
            ->setPostageTax($cart->getPostageTax())
            ->setPostageTaxRuleTitle($cart->getPostageTaxRuleTitle())
            ->setCustomerId($cart->getCustomerId())
            ->setCartId($cart->getId())
            // Frozen now, while the order still describes the cart it comes from: the delivery
            // module is about to be told about this order, and a pickup one rewrites its delivery
            // address to the store's.
            ->setCartFingerprint($this->orderFingerprint->of(
                $cart,
                $request->deliveryModuleId,
                $request->paymentModuleId,
                $request->currency?->getId(),
                $request->customerPaymentMethodId,
                $composition,
            ));

        if ($request->currency instanceof Currency) {
            $newOrder->setCurrency($request->currency);
        }

        if ($request->lang instanceof Lang) {
            $newOrder->setLang($request->lang);
        }

        $orderEvent = new OrderEvent($newOrder);

        $this->dispatcher->dispatch($orderEvent, TheliaEvents::ORDER_PAY);

        if (!$orderEvent->hasPlacedOrder()) {
            throw new TheliaProcessException('Nothing answered the order payment with an order.');
        }

        $placedOrder = $orderEvent->getPlacedOrder();

        $paymentResponse = (null !== $placedOrder->getId()) && $orderEvent->hasResponse()
            ? $orderEvent->getResponse()
            : null;

        return new OrderPaymentOutcome($placedOrder, $paymentResponse);
    }

    /**
     * Whether the unpaid order of the cart can carry this payment attempt.
     *
     * Two conditions, and both are needed. The order must still describe what the buyer
     * is paying for — the fingerprint. And the payment module must have said it can be
     * presented the same order twice: a module whose provider reference is one key on
     * the order, overwritten at each attempt, cannot, and gets a new order instead.
     *
     * A third one for an order with payment parts: it must not have been refunded while it
     * holds an instant part still marked settled. The module gave that part back with the
     * refund, and presenting the order again would charge the provider the balance alone and
     * pay the order with the part missing; the order is taken back and another is placed.
     */
    private function mayBePresentedAgain(Order $unpaidOrder, Cart $cart, OrderPaymentRequest $request, PaymentComposition $composition): bool
    {
        if (!$this->orderFingerprint->matches(
            $unpaidOrder,
            $cart,
            $request->deliveryModuleId,
            $request->paymentModuleId,
            $request->currency?->getId(),
            $request->customerPaymentMethodId,
            $composition,
        )) {
            return false;
        }

        if (!$unpaidOrder->getPaymentModuleInstance()->supportsPaymentRetry()) {
            return false;
        }

        return !$this->orderPaymentPartSettlement->wasRefundedWhileHoldingInstantParts($unpaidOrder);
    }

    /**
     * The order exists and was announced: only the payment module is asked again.
     *
     * ORDER_PAY is not raised, so nothing is written and ORDER_BEFORE_PAYMENT — the
     * confirmation e-mail and the shop notification — does not go out a second time.
     * MODULE_PAY is the same event the placement raises, so a stored payment method
     * chosen on the cart pays this attempt the way it paid the first.
     */
    private function payAgain(Order $unpaidOrder, Cart $cart): OrderPaymentOutcome
    {
        $payEvent = new OrderPaymentEvent($unpaidOrder);

        $this->paymentCartContext->within(
            $cart,
            fn () => $this->dispatcher->dispatch($payEvent, TheliaEvents::MODULE_PAY),
        );

        return new OrderPaymentOutcome($unpaidOrder, $payEvent->hasResponse() ? $payEvent->getResponse() : null);
    }

    /**
     * The shop's answer to "may this cart be ordered without an account", asked again at
     * the last moment.
     *
     * It was asked once, when the buyer said they had no account, and the cart has been
     * open to changes ever since: a product that requires an account can be added after
     * the identification, and the shop can turn the setting off between the two. This is
     * the point every path goes through — the front controller, the API and anything a
     * module calls — because it is the one that raises ORDER_PAY. Asking in
     * CheckoutValidationService instead would leave out every caller that pays without
     * validating first.
     *
     * The customer comes from the cart rather than from the session: the API is
     * stateless, and the cart is what the order is about to be built from.
     *
     * @throws GuestCheckoutNotAllowedException
     * @throws PropelException
     */
    private function refuseAGuestTheShopNoLongerAllows(Cart $cart): void
    {
        $customer = $cart->getCustomer();

        if (!$customer instanceof Customer || !$customer->isGuest()) {
            return;
        }

        if ($this->guestCheckoutPolicy->isGuestCheckoutAllowedForCart($cart)) {
            return;
        }

        throw new GuestCheckoutNotAllowedException('This order can no longer be placed without an account. Please sign in or create one.');
    }

    /**
     * The hourly allowance of payments with a stored method, asked before anything is
     * written: MODULE_PAY only spends it once the order it pays exists, and a customer
     * over the limit refused there would be left with an unpaid order, and the previous
     * one cancelled, for nothing. Nothing is spent here — StoredPaymentMethodPayment::pay()
     * still spends the slot when the provider is actually asked.
     *
     * Asked here, when the buyer pays, and nowhere earlier: the steps of the tunnel are
     * read by every page that shows the progression and by the validation of the API,
     * and a limit on paying is not something the cart is missing. An attempt that
     * presents the unpaid order again is not asked either: it writes nothing, and pay()
     * refuses it before the provider is called.
     *
     * The method is read off the cart, not off the request: MODULE_PAY pays with the one
     * the cart names, and a caller that left it out of the request would otherwise get
     * its order written before being refused.
     *
     * @throws StoredMethodPaymentRateLimitedException
     */
    private function refuseAStoredMethodPaymentOverTheAllowance(Cart $cart): void
    {
        if (null !== $cart->getCustomerPaymentMethodId()
            && $this->storedPaymentMethodPayment->customerHasExhaustedTheirAllowance((int) $cart->getCustomerId())
        ) {
            throw new StoredMethodPaymentRateLimitedException();
        }
    }

    /**
     * Take back an order whose payment did not go through.
     *
     * Two things entitle a caller to it, and the second one exists because of the first:
     * being the customer the order names, or holding a tracking token issued for that
     * very order. A guest is put out of the session the moment the order is placed —
     * before the payment module is even called, so that the next person on the browser
     * does not inherit an identity nobody signed into — so the buyer coming back from a
     * failed payment has no session left to be recognised by. The token is what they do
     * have, and it is signed, order-specific and expiring.
     *
     * @throws PropelException|\InvalidArgumentException
     */
    public function cancel(int $orderId, ?string $guestOrderToken = null): Order
    {
        $failedOrder = OrderQuery::create()->findPk($orderId);

        if (null === $failedOrder) {
            throw new \InvalidArgumentException('Order not found');
        }

        if (!$this->mayCancel($failedOrder, $guestOrderToken)) {
            throw new \InvalidArgumentException(Translator::getInstance()->trans('Received failed order id does not belong to the current customer'));
        }

        // Only an order still waiting for its payment is a failed payment. Past that,
        // "cancel" is a back-office decision with money already moved behind it, and this
        // entry point is reachable by whoever holds a tracking token — a guest coming
        // back from a payment page that did not go through. Without this, that token
        // cancelled a paid, or shipped, or already refunded order.
        if (!$failedOrder->isNotPaid()) {
            throw new \InvalidArgumentException(Translator::getInstance()->trans('This order is no longer waiting for its payment and cannot be cancelled here.'));
        }

        $failedOrder->setCancelled($this->dispatcher);

        return $failedOrder;
    }

    private function mayCancel(Order $failedOrder, ?string $guestOrderToken): bool
    {
        $customer = $this->securityContext->getCustomerUser();

        if ($customer instanceof Customer) {
            return $failedOrder->getCustomerId() === $customer->getId();
        }

        if (null === $guestOrderToken || '' === $guestOrderToken) {
            return false;
        }

        // Spent before the token is checked, so a caller pays the same whether or not the
        // token it sent turns out to be one this shop issued.
        if (!$this->guestOrderAccessLimiter->allows($guestOrderToken)) {
            return false;
        }

        // The token has to name this order and no other: one issued for an order of the
        // caller's own would otherwise cancel anybody's.
        return $this->guestOrderAccessService->findOrderForToken($guestOrderToken)?->getId() === $failedOrder->getId();
    }
}
