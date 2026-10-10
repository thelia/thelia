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
 * An administrator without the right to capture payments asked to cancel an order whose
 * authorization still holds an amount: the cancellation releases it at the provider,
 * which is a decision on the money.
 */
final class CancellationNeedsCaptureRightException extends PaymentException
{
    public function __construct(string $orderRef)
    {
        parent::__construct(\sprintf('Order %s still holds an authorized payment: cancelling it releases that amount, which needs the right to capture payments.', $orderRef));
    }
}
