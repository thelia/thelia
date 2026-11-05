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
 * An administrator asked to cancel an order whose single capture is waiting for its answer at a
 * provider that takes one capture per authorization: the cancellation would not stop it, and the
 * buyer would be charged on a cancelled order. The order is refunded once the capture is taken.
 */
final class CancellationWaitsForCaptureException extends PaymentException
{
    public function __construct(string $orderRef)
    {
        parent::__construct(\sprintf('Order %s has a capture waiting for the provider, which takes a single one: cancelling the order would not stop it. Refund the order once the capture is taken.', $orderRef));
    }
}
