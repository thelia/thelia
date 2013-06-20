<?php

namespace Thelia\Model;

use Thelia\Model\om\BaseCategory;
use Thelia\Model\CategoryQuery;


/**
 * Skeleton subclass for representing a row from the 'category' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Thelia.Model
 */
class Category extends BaseCategory
{

    /**
     * @return int number of child for the current category
     */
    public function countChild()
    {
        return CategoryQuery::countChild($this->getId());
    }

    public function getUrl()
    {

    }

    /**
     *
     * count all products for current category and sub categories
     *
     * @return int
     */
    public function countAllProducts()
    {
        $children = CategoryQuery::findAllChild($this->getId());
        array_push($children, $this);

        $countProduct = 0;

        foreach($children as $child)
        {
            $countProduct += ProductQuery::create()
                ->filterByCategory($child)
                ->count();
        }

        return $countProduct;

    }

}
