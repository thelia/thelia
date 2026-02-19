<?php

namespace Thelia\Domain\Maintenance\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\OrderQuery;

class MaintenancePurgeService
{
    /**
     * @throws PropelException
     */
    public function purgeCartsWithoutOrder(int $days): int
    {
        $threshold = $this->getThresholdDate($days);

        $cartIdsWithOrder = OrderQuery::create()
            ->select('CartId')
            ->find()
            ->toArray();

        $query = CartQuery::create()->filterByCreatedAt($threshold, Criteria::LESS_THAN);

        if (!empty($cartIdsWithOrder)) {
            $query->filterById($cartIdsWithOrder, Criteria::NOT_IN);
        }

        $query->filterByCustomerId(null, Criteria::ISNOTNULL);

        return $query->delete();
    }

    /**
     * @throws PropelException
     */
    public function purgeAnonymousCarts(int $days): int
    {
        $threshold = $this->getThresholdDate($days);

        return CartQuery::create()
            ->filterByCustomerId(null, Criteria::ISNULL)
            ->filterByCreatedAt($threshold, Criteria::LESS_THAN)
            ->delete();
    }

    /**
     * @throws PropelException
     */
    public function purgeAdminLogs(int $days): int
    {
        $threshold = $this->getThresholdDate($days);

        return AdminLogQuery::create()
            ->filterByCreatedAt($threshold, Criteria::LESS_THAN)
            ->delete();
    }

    private function getThresholdDate(int $days): \DateTime
    {
        return (new \DateTime())->modify(sprintf('-%d days', $days));
    }
}
