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
use Thelia\Model\Base\ExportJobQuery as BaseExportJobQuery;

class ExportJobQuery extends BaseExportJobQuery
{
    /**
     * Deletes the export jobs created more than $days days ago, or counts them.
     */
    public static function purgeCreatedBefore(int $days, bool $dryRun = false): int
    {
        $query = self::create()->filterByCreatedAt(new \DateTime(\sprintf('-%d days', $days)), Criteria::LESS_THAN);

        return $dryRun ? $query->count() : $query->delete();
    }
}
