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

use Propel\Runtime\Propel;
use Thelia\Model\Map\OrderStatusTransitionTableMap;
use Thelia\Model\OrderStatusActionQuery;
use Thelia\Model\OrderStatusTransition;
use Thelia\Model\OrderStatusTransitionQuery;

/**
 * Writes the transition graph and keeps the in-memory copies in step with it.
 */
final readonly class OrderStatusTransitionWriter
{
    public function __construct(
        private OrderStatusTransitionGraphProvider $graphProvider,
    ) {
    }

    /**
     * Declares the statuses reachable from a status, replacing what was declared
     * before. An empty list makes the status free again.
     *
     * @param list<int> $toStatusIds
     */
    public function replaceTargets(int $fromStatusId, array $toStatusIds): void
    {
        $connection = Propel::getConnection(OrderStatusTransitionTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            OrderStatusTransitionQuery::create()->filterByFromStatusId($fromStatusId)->delete($connection);

            foreach (array_unique($toStatusIds) as $toStatusId) {
                if ($toStatusId === $fromStatusId) {
                    continue;
                }

                (new OrderStatusTransition())
                    ->setFromStatusId($fromStatusId)
                    ->setToStatusId($toStatusId)
                    ->save($connection);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $this->graphProvider->reset();
    }

    /**
     * Drops every transition and action that names a status, before that status is deleted.
     */
    public function forgetStatus(int $statusId): void
    {
        OrderStatusTransitionQuery::create()
            ->filterByFromStatusId($statusId)
            ->_or()
            ->filterByToStatusId($statusId)
            ->delete();

        OrderStatusActionQuery::create()
            ->filterByFromStatusId($statusId)
            ->_or()
            ->filterByToStatusId($statusId)
            ->delete();

        $this->graphProvider->reset();
    }
}
