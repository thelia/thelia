<?php

namespace Thelia\Model;

use Thelia\Model\Base\ProductCategory as BaseProductCategory;

class ProductCategory extends BaseProductCategory
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * @inheritdoc
     */
    protected function addCriteriaToPositionQuery(ProductCategoryQuery $query)
    {
        $query->filterByCategoryId($this->getCategoryId());
    }
}
