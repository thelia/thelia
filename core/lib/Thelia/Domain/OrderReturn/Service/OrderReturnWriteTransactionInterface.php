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

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\OrderReturn\Exception\ReturnRequestConflictException;

/**
 * The transaction a return is written in: every path that opens a return or
 * changes a returned quantity runs its check and its write through run(),
 * naming up front the order and the order product lines it is about.
 */
interface OrderReturnWriteTransactionInterface
{
    /**
     * Run $work in the transaction, the order and its lines locked before
     * anything is read; commit it, or roll it back and rethrow. See
     * {@see OrderReturnWriteTransaction} for what is locked and in what order.
     *
     * @template T
     *
     * @param list<int>                        $orderProductIds
     * @param callable(ConnectionInterface): T $work
     *
     * @return T
     *
     * @throws ReturnRequestConflictException when the database gave up on the transaction to break a lock conflict
     */
    public function run(int $orderId, array $orderProductIds, callable $work): mixed;
}
