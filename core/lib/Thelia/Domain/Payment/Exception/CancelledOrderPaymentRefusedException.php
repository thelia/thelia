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

use Thelia\Domain\Checkout\Exception\InvalidPaymentException;

/**
 * A payment was attempted on an order that is cancelled, or whose instant parts were given
 * back by a cancellation although its status is no longer cancelled (the back office turned
 * it back to unpaid): none of its parts is settled and its payment module is not asked to
 * pay. The parts were given back, so nothing may be charged for the order any more; the
 * buyer pays a new order instead.
 */
final class CancelledOrderPaymentRefusedException extends InvalidPaymentException
{
    public static function forOrder(string $orderRef): self
    {
        return new self(\sprintf('Order %s is cancelled and can no longer be paid. Nothing was charged.', $orderRef));
    }

    public static function forOrderWhosePartsWereGivenBack(string $orderRef): self
    {
        return new self(\sprintf('Order %s can no longer be paid: its payment parts were already given back. Nothing was charged.', $orderRef));
    }
}
