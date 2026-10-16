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
 * A guest has no account to come back to, so nothing can be kept for the next order.
 */
final class GuestCannotStorePaymentMethodException extends CustomerPaymentMethodException
{
    public function __construct()
    {
        parent::__construct('A guest customer cannot store a payment method.');
    }
}
