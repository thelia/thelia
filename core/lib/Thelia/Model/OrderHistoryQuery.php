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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Model\Base\OrderHistoryQuery as BaseOrderHistoryQuery;

class OrderHistoryQuery extends BaseOrderHistoryQuery
{
    /**
     * The most recent entry of one kind on one order.
     *
     * Ordered on the primary key rather than on created_at: two entries written in the
     * same second are indistinguishable by their timestamp, and the last row written is
     * the one the caller means.
     *
     * The caller that deduplicates under a named lock passes the connection holding
     * that lock, so the check reads where the write will land even on a project that
     * splits read and write connections.
     */
    public function findLatestOfType(int $orderId, string $eventType, ?ConnectionInterface $con = null): ?OrderHistory
    {
        return self::create()
            ->filterByOrderId($orderId)
            ->filterByEventType($eventType)
            ->orderById(Criteria::DESC)
            ->findOne($con);
    }

    /**
     * The most recent transition of an order into one status.
     *
     * The status a transition lands on lives inside the JSON payload, and matching it
     * with SQL would mean a LIKE on that text: it would match a status code that is
     * the prefix of another one, and it would match a payload where the code sits
     * under "from" instead of "to". The status changes of one order are a handful of
     * rows, so they are read back and decoded, and the answer comes from the "to" key
     * itself rather than from the shape of the string around it.
     *
     * The rows are walked newest first, so the first match is the last time the order
     * reached that status.
     */
    public function findLastStatusChangeTo(int $orderId, string $statusCode): ?OrderHistory
    {
        $statusChanges = self::create()
            ->filterByOrderId($orderId)
            ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->orderById(Criteria::DESC)
            ->find();

        foreach ($statusChanges as $statusChange) {
            if ($statusCode === ($statusChange->getDecodedPayload()['to'] ?? null)) {
                return $statusChange;
            }
        }

        return null;
    }
}
