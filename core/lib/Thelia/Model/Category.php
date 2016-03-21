<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\Category\CategoryEvent;
use Thelia\Files\FileModelParentInterface;
use Thelia\Model\Base\Category as BaseCategory;
use Thelia\Core\Event\TheliaEvents;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;

class Category extends BaseCategory implements FileModelParentInterface
{
    use ModelEventDispatcherTrait;

    use PositionManagementTrait;

    use UrlRewritingTrait;

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
    public function getRewrittenUrlViewName()
    {
        return 'category';
    }

    /**
     *
     * count all products for current category and sub categories
     *
     * /!\ the number of queries is exponential, use it with caution
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
     * Get the root category
     * @param  int   $categoryId
     * @return mixed
     */
    public function getRoot($categoryId)
    {
        $category = CategoryQuery::create()->findPk($categoryId);

        if (0 !== $category->getParent()) {
            $parentCategory = CategoryQuery::create()->findPk($category->getParent());

            if (null !== $parentCategory) {
                $categoryId = $this->getRoot($parentCategory->getId());
            }
        }

        return $categoryId;
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
        $this->markRewrittenUrlObsolete();

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

    /**
     * Overload for the position management
     * @param Base\ProductCategory $productCategory
     * @inheritdoc
     */
    protected function doAddProductCategory($productCategory)
    {
        parent::doAddProductCategory($productCategory);

        $productCategoryPosition = ProductCategoryQuery::create()
            ->filterByCategoryId($productCategory->getCategoryId())
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        $productCategory->setPosition($productCategoryPosition !== null ? $productCategoryPosition->getPosition() + 1 : 1);
    }
}
