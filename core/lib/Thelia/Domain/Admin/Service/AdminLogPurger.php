<?php

namespace Thelia\Domain\Admin\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\AdminLogQuery;

class AdminLogPurger
{
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
