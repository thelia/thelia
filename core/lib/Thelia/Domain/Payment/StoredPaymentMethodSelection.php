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

use Thelia\Domain\Payment\Exception\PaymentMethodNotOwnedException;
use Thelia\Domain\Payment\Exception\StoredMethodNotSelectableException;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod;

/**
 * Which stored payment method a cart may be paid with.
 *
 * The same rule answers when the buyer chooses the method and again when the order is
 * paid: the method is the customer's own, it was stored through the payment module the
 * cart is paid through, and it has not expired. Between the two, the method can be
 * forgotten, the customer can change, the module can change: the choice written on the
 * cart is a wish, not a proof.
 */
final readonly class StoredPaymentMethodSelection
{
    public function __construct(
        private CustomerPaymentMethodService $customerPaymentMethods,
    ) {
    }

    /**
     * The method to write on the cart, null when the buyer pays with a new one.
     *
     * @throws PaymentMethodNotOwnedException     when the cart has no customer, or the method is not theirs
     * @throws StoredMethodNotSelectableException
     */
    public function methodToChooseFor(Cart $cart, ?int $paymentModuleId, ?int $customerPaymentMethodId): ?CustomerPaymentMethod
    {
        if (null === $customerPaymentMethodId) {
            return null;
        }

        return $this->usableMethod($cart->getCustomer(), $paymentModuleId, $customerPaymentMethodId);
    }

    /**
     * @throws PaymentMethodNotOwnedException     when there is no customer, or the method is not theirs
     * @throws StoredMethodNotSelectableException
     */
    public function usableMethod(?Customer $customer, ?int $paymentModuleId, int $customerPaymentMethodId): CustomerPaymentMethod
    {
        if (!$customer instanceof Customer) {
            throw new PaymentMethodNotOwnedException();
        }

        if ($customer->isGuest()) {
            throw StoredMethodNotSelectableException::forAGuest();
        }

        $method = $this->customerPaymentMethods->findOwned($customer, $customerPaymentMethodId);

        if (!$method instanceof CustomerPaymentMethod) {
            throw new PaymentMethodNotOwnedException();
        }

        if (null === $paymentModuleId || $method->getPaymentModuleId() !== $paymentModuleId) {
            throw StoredMethodNotSelectableException::ofAnotherPaymentModule();
        }

        if ($method->isExpiredOn(new \DateTimeImmutable('today'))) {
            throw StoredMethodNotSelectableException::expired();
        }

        return $method;
    }
}
