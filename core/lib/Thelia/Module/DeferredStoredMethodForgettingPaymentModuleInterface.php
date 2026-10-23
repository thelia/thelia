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

use Thelia\Model\CustomerPaymentMethod;

/**
 * A payment module that needs, to forget a stored method, data that goes with its row.
 *
 * The anonymization and the deletion of a customer drop the methods in their transaction
 * and tell the providers only once it is committed: by then the row is gone, and with it
 * every row a foreign key on delete cascade ties to it, the module's own included. Such a
 * module reads what it needs here, while the row is still there, and hands back the call
 * that asks its provider.
 */
interface DeferredStoredMethodForgettingPaymentModuleInterface extends PaymentModuleWithStoredMethodsInterface
{
    /**
     * Called in the transaction that drops the method, before the drop: read only, never
     * a call to the provider, the erasure does not wait on a third party.
     *
     * @return \Closure(): void called once the drop is committed, in place of
     *                          forgetStoredMethod(); it may throw as forgetStoredMethod()
     *                          does, the core then logs the failure by its class only
     */
    public function prepareToForgetStoredMethod(CustomerPaymentMethod $method): \Closure;
}
