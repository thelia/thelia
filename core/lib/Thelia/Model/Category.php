<?php

namespace Thelia\Model;

use Thelia\Core\Event\Category\CategoryEvent;
use Thelia\Model\Base\Category as BaseCategory;

use Thelia\Core\Event\TheliaEvents;
use Propel\Runtime\Connection\ConnectionInterface;

class Category extends BaseCategory
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    use \Thelia\Model\Tools\UrlRewritingTrait;

    /**
     * @return int number of child for the current category
     */
    public function countChild()
    {
        return CategoryQuery::countChild($this->getId());
    }

    /**
     * {@inheritDoc}
     */
    protected function getRewrittenUrlViewName()
    {
        return 'category';
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

        foreach ($children as $child) {
            $countProduct += ProductQuery::create()
                ->filterByCategory($child)
                ->count();
        }

        return $countProduct;
    }

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByParent($this->getParent());
    }

    public function deleteProducts(ConnectionInterface $con = null)
    {
        $productsCategories = ProductCategoryQuery::create()
            ->filterByCategoryId($this->getId())
            ->filterByDefaultCategory(1)
            ->find($con);

        if ($productsCategories) {
            foreach ($productsCategories as $productCategory) {
                $product = $productCategory->getProduct();
                if ($product) {
                    $product->delete($con);
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATECATEGORY, new CategoryEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATECATEGORY, new CategoryEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATECATEGORY, new CategoryEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATECATEGORY, new CategoryEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETECATEGORY, new CategoryEvent($this));
        $this->reorderBeforeDelete(
            array(
                "parent" => $this->getParent(),
            )
        );
        $this->deleteProducts($con);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->markRewritenUrlObsolete();

        //delete all subcategories
        $subCategories = CategoryQuery::findAllChild($this->getId());

        foreach ($subCategories as $category) {
            if (!is_null($this->dispatcher)) {
                $category->setDispatcher($this->getDispatcher());
            }

            $category->delete();
        }

        $this->dispatchEvent(TheliaEvents::AFTER_DELETECATEGORY, new CategoryEvent($this));
    }
}
