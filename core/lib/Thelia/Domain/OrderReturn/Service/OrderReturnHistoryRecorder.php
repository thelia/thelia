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

namespace Thelia\Domain\OrderReturn\Service;

use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\OrderReturn;

/**
 * The one place that writes the line announcing a return on the history of its
 * order, and the payload every line about a return carries.
 *
 * RecordOrderReturnHistoryListener calls it after ORDER_RETURN_CREATE, the API
 * processors that open a return call it themselves because they cannot
 * dispatch that event (Thelia\Action\OrderReturn would create the return a
 * second time), and a theme that writes a return its own way calls it too.
 * Call it once the write is committed, before the e-mail of
 * OrderReturnStatusEmailDispatcher, and never when the creation was refused. A
 * caller that dispatches ORDER_RETURN_CREATE must not call it as well: the
 * listener already has.
 *
 * The author of the line is the one OrderHistoryActorResolver names for the
 * request: the user of the security token on the API, the one in session
 * elsewhere, and the system when there is neither (the console, a worker).
 */
final readonly class OrderReturnHistoryRecorder
{
    public function __construct(
        private OrderHistoryRecorder $orderHistoryRecorder,
    ) {
    }

    public function recordOpened(OrderReturn $orderReturn): void
    {
        $orderId = $orderReturn->getOrderId();

        if (null === $orderId) {
            return;
        }

        $this->orderHistoryRecorder->record(
            $orderId,
            OrderHistoryEventType::RETURN_OPENED->value,
            $this->referencePayload($orderReturn),
        );
    }

    /**
     * The reference of the return, which is what tells two returns of the same order
     * apart — both for the merchant reading the line and for the deduplication the
     * recorder applies on an identical payload.
     *
     * @return array<string, string>
     */
    public function referencePayload(OrderReturn $orderReturn): array
    {
        $reference = $orderReturn->getRef();

        return null === $reference || '' === $reference ? [] : ['return_ref' => $reference];
    }
}
