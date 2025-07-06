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

use Thelia\Model\Base\ProductCategory as BaseProductCategory;
use Thelia\Model\Tools\PositionManagementTrait;

class ProductCategory extends BaseProductCategory
{
    use PositionManagementTrait;

    protected function addCriteriaToPositionQuery(ProductCategoryQuery $query): void
    {
        $query->filterByCategoryId($this->getCategoryId());
    }
}
