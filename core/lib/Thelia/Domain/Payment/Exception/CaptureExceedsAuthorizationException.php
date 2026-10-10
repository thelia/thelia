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

use Thelia\Domain\Payment\Service\PaymentAmount;

final class CaptureExceedsAuthorizationException extends PaymentException
{
    public function __construct(
        private readonly string $orderRef,
        private readonly string $requestedAmount,
        private readonly string $remainingToCapture,
    ) {
        parent::__construct(\sprintf(
            'Order %s: a capture of %s exceeds the %s its authorization still holds.',
            $orderRef,
            PaymentAmount::forMessage($requestedAmount),
            PaymentAmount::forMessage($remainingToCapture),
        ));
    }

    public function getOrderRef(): string
    {
        return $this->orderRef;
    }

    public function getRequestedAmount(): string
    {
        return $this->requestedAmount;
    }

    public function getRemainingToCapture(): string
    {
        return $this->remainingToCapture;
    }
}
