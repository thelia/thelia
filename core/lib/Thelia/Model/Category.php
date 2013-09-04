<?php

namespace Thelia\Model;

use Thelia\Core\Event\CategoryEvent;
use Thelia\Model\Base\Category as BaseCategory;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Tools\URL;
use Thelia\Core\Event\TheliaEvents;
use Propel\Runtime\Connection\ConnectionInterface;

class Category extends BaseCategory
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    /**
     * @return int number of child for the current category
     */
    public function countChild()
    {
        return CategoryQuery::countChild($this->getId());
    }

    public function getUrl($locale)
    {
        return URL::init()->retrieve('category', $this->getId(), $locale);
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

		return $last != null ? $last->getPosition() + 1 : 1;
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

    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATECATEGORY, new CategoryEvent($this));

        return true;
    }

    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATECATEGORY, new CategoryEvent($this));
    }

    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATECATEGORY, new CategoryEvent($this));

        return true;
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATECATEGORY, new CategoryEvent($this));
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETECATEGORY, new CategoryEvent($this));
    }

    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETECATEGORY, new CategoryEvent($this));
    }
}