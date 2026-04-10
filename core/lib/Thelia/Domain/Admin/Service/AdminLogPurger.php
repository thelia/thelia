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
        return (new \DateTime())->modify(\sprintf('-%d days', $days));
    }
}
