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

namespace Thelia\Domain\Order\Exception;

/**
 * The transition graph configured in the back office does not allow an order
 * to go from its current status to the requested one.
 */
final class OrderStatusTransitionRefusedException extends OrderException
{
    public const TRANSITION_REFUSED = 300;

    public function __construct(
        private readonly string $orderRef,
        private readonly string $fromStatusCode,
        private readonly string $toStatusCode,
    ) {
        parent::__construct(
            \sprintf(
                'Order %s cannot go from status "%s" to status "%s": this transition is not allowed.',
                $orderRef,
                $fromStatusCode,
                $toStatusCode,
            ),
            self::TRANSITION_REFUSED,
        );
    }

    public function getOrderRef(): string
    {
        return $this->orderRef;
    }

    public function getFromStatusCode(): string
    {
        return $this->fromStatusCode;
    }

    public function getToStatusCode(): string
    {
        return $this->toStatusCode;
    }
}
