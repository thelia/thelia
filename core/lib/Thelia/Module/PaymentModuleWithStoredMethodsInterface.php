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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Payment\Exception\StoredMethodRefusedException;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Order;

/**
 * A payment module that can keep a customer's payment method at its provider and pay
 * a later order with it.
 *
 * Once the provider has answered, the module records the method through
 * Thelia\Domain\Payment\CustomerPaymentMethodService::register(), with the opaque
 * reference the provider handed back and a label the customer can recognize ("Visa
 * ending 4242"). Never a full card number nor a security code: the provider keeps
 * those, the shop does not.
 */
interface PaymentModuleWithStoredMethodsInterface extends PaymentModuleInterface
{
    /**
     * Whether the module offers stored methods right now; a module can depend on its
     * configuration or on the provider account.
     */
    public function supportsStoredMethods(): bool;

    /**
     * Pays the order with a method the customer stored earlier. Same contract as
     * pay(): a response is sent to the browser (a 3-D Secure challenge, for one), null
     * means the payment went through without the customer.
     */
    public function payWithStoredMethod(Order $order, CustomerPaymentMethod $method): ?Response;

    /**
     * Asks the provider to forget the method. Called before the shop drops the row,
     * except by the anonymization and the deletion of the customer, which call it once
     * the drop is committed: a module that then needs data going with the row implements
     * DeferredStoredMethodForgettingPaymentModuleInterface.
     *
     * A refusal passes what the provider said as the providerReason of the exception,
     * never as its message nor as a previous exception: the message is answered to the
     * customer and logged, and a provider message may quote the token.
     *
     * @throws StoredMethodRefusedException when the provider refuses: the shop then
     *                                      keeps the method
     */
    public function forgetStoredMethod(CustomerPaymentMethod $method): void;
}
