<?php

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
use Thelia\Model\Base\ContentQuery as BaseContentQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'content' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ContentQuery extends BaseContentQuery
{
    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolderQuery::filterByPosition
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        return parent::filterByPosition($position, $comparison);
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolderQuery::orderByPosition
     */
    public function orderByPosition($order = Criteria::ASC)
    {
        return parent::orderByPosition($order);
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolderQuery::groupByPosition
     */
    public function groupByPosition()
    {
        return parent::groupByPosition();
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolderQuery::findOneByPosition
     */
    public function findOneByPosition($position)
    {
        return parent::findOneByPosition($position);
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolderQuery::findByPosition
     */
    public function findByPosition($position)
    {
        return parent::findByPosition($position);
    }
}
// ContentQuery
