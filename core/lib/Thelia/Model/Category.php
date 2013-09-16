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

    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * @return int number of child for the current category
     */
    public function countChild()
    {
        return CategoryQuery::countChild($this->getId());
    }

    public function getUrl($locale)
    {
        return URL::getInstance()->retrieve('category', $this->getId(), $locale)->toString();
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
        $this->setPosition($this->getNextPosition($this->parent));

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

        return true;
    }

    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETECATEGORY, new CategoryEvent($this));
    }
}