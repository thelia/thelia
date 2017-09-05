<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Core\Event\Category\CategoryEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
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
     * @param bool|string $productVisibility: true (default) to count only visible products, false to count only hidden
     *                    products, or * to count all products.
     * @return int
     */
    public function countAllProducts($productVisibility = true)
    {
        $children = CategoryQuery::findAllChild($this->getId());
        array_push($children, $this);

        $query = ProductQuery::create();

        if ($productVisibility !== '*') {
            $query->filterByVisible($productVisibility);
        }

        $query
            ->useProductCategoryQuery()
                ->filterByCategory(new ObjectCollection($children), Criteria::IN)
            ->endUse();

        return $query->count();
    }

    /**
     *
     * count visible products only for current category and sub categories
     *
     * /!\ the number of queries is exponential, use it with caution
     *
     * @return int
     */
    public function countAllProductsVisibleOnly()
    {
        return $this->countAllProducts(true);
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
     * @param CategoryQuery $query
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
            /** @var ProductCategory $productCategory */
            foreach ($productsCategories as $productCategory) {
                if (null !== $product = $productCategory->getProduct()) {
                    $this->dispatchEvent(TheliaEvents::PRODUCT_DELETE, new ProductDeleteEvent($product->getId()));
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
