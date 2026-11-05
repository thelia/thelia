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

namespace Thelia\Domain\Order\Service;

use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;

/**
 * What a change of status will do to the stock, worked out once before its transaction (see
 * PaymentStockLock): the stock rows to lock, and the operation the listeners answered for a
 * payment, so that the transaction does not ask them again.
 */
final readonly class StatusStockPlan
{
    /**
     * @param list<int> $rows the sale elements whose stock row is locked, in ascending id
     */
    public function __construct(
        public int $previousStatusId,
        public int $newStatusId,
        public ?GetStockUpdateOperationOnOrderStatusChangeEvent $operation,
        public array $rows,
    ) {
    }

    /**
     * The operation answered before the transaction, when it is still the one the order would get
     * now: the status read under the lock of the order is the status it was worked out on. A
     * status another request moved in between gives no answer, and the transaction asks again.
     */
    public function operationFor(int $statusIdUnderLock, int $statusIdOfTheOrder, int $newStatusId): ?GetStockUpdateOperationOnOrderStatusChangeEvent
    {
        return $this->previousStatusId === $statusIdUnderLock
            && $this->previousStatusId === $statusIdOfTheOrder
            && $this->newStatusId === $newStatusId
                ? $this->operation
                : null;
    }
}
