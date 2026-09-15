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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\OrderHistoryQuery;

/**
 * Applies a retention period to the timestamped history of orders.
 *
 * The rows are dated by when the gesture happened, not by the age of the order,
 * so a long-running order keeps its recent entries and loses only the old ones.
 * Deleting an entry never touches the order it belongs to: the amounts, the
 * invoice number and the current status are the accounting record and live on
 * the order itself.
 */
class OrderHistoryPurger
{
    /**
     * @throws PropelException
     */
    public function purgeOrderHistory(int $days): int
    {
        return $this->expiredOrderHistory($days)->delete();
    }

    /**
     * @throws PropelException
     */
    public function countOrderHistory(int $days): int
    {
        return $this->expiredOrderHistory($days)->count();
    }

    private function expiredOrderHistory(int $days): OrderHistoryQuery
    {
        return OrderHistoryQuery::create()
            ->filterByCreatedAt($this->getThresholdDate($days), Criteria::LESS_THAN);
    }

    private function getThresholdDate(int $days): \DateTime
    {
        return (new \DateTime())->modify(\sprintf('-%d days', $days));
    }
}
