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
 * A stored payment method the cart cannot be paid with. Whose method it is, when it is
 * not the buyer's, is not said: PaymentMethodNotOwnedException answers a method of
 * another customer and a missing one alike.
 */
final class StoredMethodNotSelectableException extends CustomerPaymentMethodException
{
    public static function forAGuest(): self
    {
        return new self('A guest has no stored payment method to pay with.');
    }

    public static function ofAnotherPaymentModule(): self
    {
        return new self('This stored payment method belongs to another payment module than the one chosen.');
    }

    public static function expired(): self
    {
        return new self('This stored payment method has expired.');
    }
}
