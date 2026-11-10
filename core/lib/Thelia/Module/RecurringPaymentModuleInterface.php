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

use Thelia\Domain\Subscription\RecurringChargeResult;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Order;

/**
 * A payment module that can charge a stored method while the customer is away, for the
 * installments of a subscription.
 *
 * The shop keeps the billing calendar: it creates the installment order, then asks the
 * module to charge it once, outside any request and any database transaction. The module
 * never sends anything to a browser and never changes the order status itself: it answers
 * with a RecurringChargeResult and the shop moves the order and the subscription.
 *
 * The first order of a subscription goes through the checkout as usual and must leave a
 * stored method behind (CustomerPaymentMethodService::register()), even when it costs
 * nothing because of a trial: the module then registers the method without charging.
 */
interface RecurringPaymentModuleInterface extends PaymentModuleWithStoredMethodsInterface
{
    /**
     * Whether the module can charge a stored method off session right now; a module can
     * depend on its configuration or on the provider account.
     */
    public function supportsRecurringCharges(): bool;

    /**
     * Charges the total of the order to the stored method, without the customer.
     *
     * The idempotency key is the same for every attempt of the same installment: a module
     * whose provider accepts one passes it, so that a charge retried after a timeout is not
     * taken twice. A module that cannot reach its provider answers
     * RecurringChargeResult::retryable() rather than throwing.
     */
    public function chargeOffSession(Order $order, CustomerPaymentMethod $method, string $idempotencyKey): RecurringChargeResult;
}
