<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\ProductQuery as BaseProductQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'product' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ProductQuery extends BaseProductQuery
{
    /**
     * @inheritdoc
     * @deprecated since 2.3, and will be removed in 2.4, please use ProductCategoryQuery::filterByPosition
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        return parent::filterByPosition($position, $comparison);
    }

    /**
     * @inheritdoc
     * @deprecated since 2.3, and will be removed in 2.4, please use ProductCategoryQuery::orderByPosition
     */
    public function orderByPosition($order = Criteria::ASC)
    {
        return parent::orderByPosition($order);
    }

    /**
     * @inheritdoc
     * @deprecated since 2.3, and will be removed in 2.4, please use ProductCategoryQuery::groupByPosition
     */
    public function groupByPosition()
    {
        return parent::groupByPosition();
    }

    /**
     * @inheritdoc
     * @deprecated since 2.3, and will be removed in 2.4, please use ProductCategoryQuery::findOneByPosition
     */
    public function findOneByPosition($position)
    {
        return parent::findOneByPosition($position);
    }

    /**
     * @inheritdoc
     * @deprecated since 2.3, and will be removed in 2.4, please use ProductCategoryQuery::findByPosition
     */
    public function findByPosition($position)
    {
        return parent::findByPosition($position);
    }
}
// ProductQuery
