<?php

namespace Thelia\Model;

use Thelia\Model\Base\ProductCategory as BaseProductCategory;

class ProductCategory extends BaseProductCategory
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementDelegateTrait;

    public function addCriteriaToPositionQuery($query){
        $query->filterByCategoryId($this->getCategoryId());
    }

}
