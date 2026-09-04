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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Checkout\Exception\GuestCheckoutNotAllowedException;
use Thelia\Domain\Order\Service\GuestOrderAccessLimiter;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
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
    ) {
    }

    /**
     * @throws GuestCheckoutNotAllowedException when the shop stopped allowing this cart to be ordered without an account
     * @throws \Exception
     */
    public function pay(
        Cart $cart,
        int $deliveryAddressId,
        int $invoiceAddressId,
        int $deliveryModuleId,
        int $paymentModuleId,
    ): ?Response {
        $this->refuseAGuestTheShopNoLongerAllows($cart);

        $newOrder = (new Order())
            ->setDeliveryOrderAddressId($deliveryAddressId)
            ->setInvoiceOrderAddressId($invoiceAddressId)
            ->setPaymentModuleId($paymentModuleId)
            ->setDeliveryModuleId($deliveryModuleId)
            ->setPostage((string) $cart->getPostage())
            ->setPostageTax($cart->getPostageTax())
            ->setPostageTaxRuleTitle($cart->getPostageTaxRuleTitle())
            ->setCustomerId($cart->getCustomerId())
            ->setCartId($cart->getId());

        $orderEvent = new OrderEvent($newOrder);

        $this->dispatcher->dispatch($orderEvent, TheliaEvents::ORDER_PAY);

        $placedOrder = $orderEvent->getPlacedOrder();

        if ((null !== $placedOrder->getId()) && $orderEvent->hasResponse()) {
            return $orderEvent->getResponse();
        }

        return null;
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

        $failedOrder->setCancelled();

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
