<?php

namespace Thelia\Model;

use Thelia\Model\Base\Category as BaseCategory;
use Propel\Runtime\ActiveQuery\Criteria;

class Category extends BaseCategory {
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
     * Create a new category.
     *
     * @param string $title the category title
     * @param int $parent the ID of the parent category
     * @param string $locale the locale of the title
     */
    public function create($title, $parent, $locale)
    {
    	$this
	    	->setLocale($locale)
	    	->setTitle($title)
	    	->setParent($parent)
	    	->setVisible(1)
	    	->setPosition($this->getNextPosition($parent))
    	;

    	$this->save();
     }

     public function getNextPosition($parent) {

		$last = CategoryQuery::create()
			->filterByParent($parent)
			->orderByPosition(Criteria::DESC)
			->limit(1)
			->findOne()
		;

		return $last->getPosition() + 1;
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
