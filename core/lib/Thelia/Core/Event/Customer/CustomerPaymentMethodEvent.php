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

namespace Thelia\Core\Event\Customer;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CustomerPaymentMethod;

/**
 * Tells that a stored payment method was recorded or dropped, once the service has
 * committed its transaction: when the caller holds a transaction of its own, the change
 * is only final with the caller's commit.
 *
 * Dispatched with TheliaEvents::CUSTOMER_PAYMENT_METHOD_CREATE and
 * TheliaEvents::CUSTOMER_PAYMENT_METHOD_DELETE. A listener never logs nor serializes
 * the method's token.
 */
final class CustomerPaymentMethodEvent extends ActionEvent
{
    public function __construct(private readonly CustomerPaymentMethod $paymentMethod)
    {
    }

    public function getPaymentMethod(): CustomerPaymentMethod
    {
        return $this->paymentMethod;
    }
}
