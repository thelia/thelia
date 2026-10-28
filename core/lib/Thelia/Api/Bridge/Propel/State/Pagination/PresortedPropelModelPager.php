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

namespace Thelia\Api\Bridge\Propel\State\Pagination;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Util\PropelModelPager;

/**
 * A page whose members were already chosen and ordered by someone else, with the size of the
 * whole set they were taken from: the query only loads the members, it neither counts nor
 * slices anything.
 */
final class PresortedPropelModelPager extends PropelModelPager
{
    public function __construct(ModelCriteria $pageQuery, int $maxPerPage, int $page, int $totalResults)
    {
        parent::__construct($pageQuery, $maxPerPage);

        $this->setPage($page);
        $this->setNbResults($totalResults);
        $this->setLastPage($maxPerPage > 0 ? (int) ceil($totalResults / $maxPerPage) : 0);
    }

    public function init(?ConnectionInterface $con = null): void
    {
        // Counted and sliced upstream: asking the query again would count the page.
    }
}
