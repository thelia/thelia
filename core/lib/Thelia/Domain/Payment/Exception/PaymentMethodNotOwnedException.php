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

namespace Thelia\Domain\Payment\Exception;

/**
 * The stored payment method belongs to another customer, or no longer exists. Both read
 * the same, so a customer cannot learn which ids are taken.
 */
final class PaymentMethodNotOwnedException extends CustomerPaymentMethodException
{
    public function __construct()
    {
        parent::__construct('This stored payment method does not belong to this customer.');
    }
}
