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

use Thelia\Model\Base\ProductCategory as BaseProductCategory;

class ProductCategory extends BaseProductCategory
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * {@inheritdoc}
     */
    protected function addCriteriaToPositionQuery(ProductCategoryQuery $query)
    {
        $query->filterByCategoryId($this->getCategoryId());
    }
}
