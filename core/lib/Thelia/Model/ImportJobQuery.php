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
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Model\Base\ImportJobQuery as BaseImportJobQuery;
use Thelia\Model\Map\ImportJobTableMap;

class ImportJobQuery extends BaseImportJobQuery
{
    /**
     * The import jobs created more than $days days ago; a failed one only after
     * $failedDays days.
     */
    public static function createdBefore(int $days, ?int $failedDays = null): self
    {
        $query = self::create()->filterByCreatedAt(new \DateTime(\sprintf('-%d days', $days)), Criteria::LESS_THAN);

        if (null !== $failedDays) {
            // A job past the first period goes unless it failed; a failed one waits
            // for the second. Two named conditions, so the OR is parenthesized.
            $query
                ->condition('notFailed', ImportJobTableMap::COL_STATUS.' <> ?', JobStatus::FAILED->value)
                ->condition('failureExpired', ImportJobTableMap::COL_CREATED_AT.' < ?', (new \DateTime(\sprintf('-%d days', $failedDays)))->format('Y-m-d H:i:s'))
                ->where(['notFailed', 'failureExpired'], Criteria::LOGICAL_OR);
        }

        return $query;
    }
}
