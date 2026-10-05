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
use Thelia\Model\Base\ImportJobQuery as BaseImportJobQuery;

class ImportJobQuery extends BaseImportJobQuery
{
    /**
     * The import jobs created more than $days days ago.
     */
    public static function createdBefore(int $days): self
    {
        return self::create()->filterByCreatedAt(new \DateTime(\sprintf('-%d days', $days)), Criteria::LESS_THAN);
    }
}
