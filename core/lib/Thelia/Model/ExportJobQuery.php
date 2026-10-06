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
use Thelia\Model\Base\ExportJobQuery as BaseExportJobQuery;
use Thelia\Model\Map\ExportJobTableMap;

class ExportJobQuery extends BaseExportJobQuery
{
    /**
     * Deletes the export jobs created more than $days days ago, or counts them; a
     * failed one only after $failedDays days.
     */
    public static function purgeCreatedBefore(int $days, bool $dryRun = false, ?int $failedDays = null): int
    {
        $query = self::create()->filterByCreatedAt(new \DateTime(\sprintf('-%d days', $days)), Criteria::LESS_THAN);

        if (null !== $failedDays) {
            $query->where(\sprintf('%s <> ? OR %s < ?', ExportJobTableMap::COL_STATUS, ExportJobTableMap::COL_CREATED_AT), [JobStatus::FAILED->value, (new \DateTime(\sprintf('-%d days', $failedDays)))->format('Y-m-d H:i:s')]);
        }

        return $dryRun ? $query->count() : $query->delete();
    }
}
