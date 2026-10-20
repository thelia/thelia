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

namespace Thelia\Domain\Payment;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Domain\Payment\Exception\CustomerPaymentMethodException;
use Thelia\Domain\Payment\Exception\StoredMethodPaymentRateLimitedException;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Order;
use Thelia\Module\PaymentModuleInterface;
use Thelia\Module\PaymentModuleWithStoredMethodsInterface;

/**
 * Pays an order with the stored method chosen on its cart.
 *
 * The choice is read again off the cart the order was placed from, which outlives the
 * placement until the order is paid, and checked again against the customer of the
 * order: a method forgotten, expired or no longer the customer's since it was chosen
 * leaves the order to be paid with a new method, which is what the buyer gets when
 * nothing was stored. The log says so, by ids: never with the token.
 */
final readonly class StoredPaymentMethodPayment
{
    public function __construct(
        private StoredPaymentMethodSelection $selection,
        #[Autowire(service: 'limiter.stored_method_payment')]
        private RateLimiterFactoryInterface $storedMethodPaymentLimiter,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * The method the order is to be paid with, null for a new one.
     */
    public function methodChosenFor(Order $order, PaymentModuleWithStoredMethodsInterface $paymentModule): ?CustomerPaymentMethod
    {
        $chosenMethodId = null === $order->getCartId()
            ? null
            : CartQuery::create()->findPk($order->getCartId())?->getCustomerPaymentMethodId();

        if (null === $chosenMethodId) {
            return null;
        }

        if (!$paymentModule->supportsStoredMethods()) {
            $this->logger->warning(\sprintf(
                'Order #%d is paid with a new payment method: its payment module #%d no longer offers stored methods.',
                (int) $order->getId(),
                (int) $order->getPaymentModuleId(),
            ));

            return null;
        }

        try {
            return $this->selection->usableMethod($order->getCustomer(), $order->getPaymentModuleId(), $chosenMethodId);
        } catch (CustomerPaymentMethodException $unusable) {
            $this->logger->warning(\sprintf(
                'Order #%d is paid with a new payment method: the stored payment method #%d chosen on cart #%d can no longer pay it (%s).',
                (int) $order->getId(),
                $chosenMethodId,
                (int) $order->getCartId(),
                $unusable::class,
            ));

            return null;
        }
    }

    /**
     * The method chosen on a cart about to be ordered, when it can still pay with the
     * payment module the order is placed with; null when MODULE_PAY would pay with a new
     * one, and so spend no slot of the allowance: the same answers as methodChosenFor(),
     * a module that does not keep methods, or no longer offers them, included. Nothing is
     * logged: no order exists yet.
     */
    public function usableMethodChosenOn(Cart $cart, PaymentModuleInterface $paymentModule, int $paymentModuleId): ?CustomerPaymentMethod
    {
        $chosenMethodId = $cart->getCustomerPaymentMethodId();

        if (null === $chosenMethodId) {
            return null;
        }

        if (!$paymentModule instanceof PaymentModuleWithStoredMethodsInterface || !$paymentModule->supportsStoredMethods()) {
            return null;
        }

        try {
            return $this->selection->usableMethod($cart->getCustomer(), $paymentModuleId, $chosenMethodId);
        } catch (CustomerPaymentMethodException) {
            return null;
        }
    }

    /**
     * Each payment with a stored method is counted against the customer, before the
     * provider is called: nobody types a card number here, so nothing else slows down a
     * session that pays again and again.
     *
     * @throws StoredMethodPaymentRateLimitedException
     */
    public function pay(PaymentModuleWithStoredMethodsInterface $paymentModule, Order $order, CustomerPaymentMethod $method): ?Response
    {
        if (!$this->limiterFor((int) $order->getCustomerId())->consume()->isAccepted()) {
            throw new StoredMethodPaymentRateLimitedException();
        }

        return $paymentModule->payWithStoredMethod($order, $method);
    }

    /**
     * Whether the customer has already spent their hourly allowance of payments with a
     * stored method, without spending a slot itself: a placement asks this before the
     * order it would pay is even written, so a customer already over the limit is
     * refused before an order is left unpaid on their account rather than after. The
     * slot is still spent once, in pay() above, when the provider is actually called.
     */
    public function customerHasExhaustedTheirAllowance(int $customerId): bool
    {
        // isAccepted() is not the answer here: SlidingWindowLimiter::reserve() hardcodes
        // it to true for a zero-token request, whatever the window already holds — a
        // peek asks nothing, and asking for nothing is always granted. The remaining
        // count is what actually reflects the window, and is what pay() is about to
        // compare a real request of one token against.
        return $this->limiterFor($customerId)->consume(0)->getRemainingTokens() <= 0;
    }

    private function limiterFor(int $customerId): LimiterInterface
    {
        return $this->storedMethodPaymentLimiter->create('customer:'.$customerId);
    }
}
