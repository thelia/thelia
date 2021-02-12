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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Files\FileModelParentInterface;
use Thelia\Model\Base\Category as BaseCategory;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;

class Category extends BaseCategory implements FileModelParentInterface
{
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
     * count all products for current category and sub categories.
     *
     * /!\ the number of queries is exponential, use it with caution
     *
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
     * count visible products only for current category and sub categories.
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
     * Get the root category.
     *
     * @param int $categoryId
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
     * Calculate next position relative to our parent.
     *
     * @param CategoryQuery $query
     */
    protected function addCriteriaToPositionQuery($query): void
    {
        $query->filterByParent($this->getParent());
    }

    public function deleteProducts(ConnectionInterface $con = null): void
    {
        $productsCategories = ProductCategoryQuery::create()
            ->filterByCategoryId($this->getId())
            ->filterByDefaultCategory(1)
            ->find($con);

        if (
            null !== $con
            && method_exists($con, 'getEventDispatcher')
            && null !== $con->getEventDispatcher()
        ) {
            $eventDispatcher = $con->getEventDispatcher();
            foreach ($productsCategories as $productCategory) {
                if (null !== $product = $productCategory->getProduct()) {
                    $eventDispatcher->dispatch(new ProductDeleteEvent($product->getId()), TheliaEvents::PRODUCT_DELETE);
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

        parent::preInsert($con);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                'parent' => $this->getParent(),
            ]
        );
        $this->deleteProducts($con);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null): void
    {
        parent::postDelete($con);

        $this->markRewrittenUrlObsolete();

        //delete all subcategories
        $subCategories = CategoryQuery::findAllChild($this->getId());

        foreach ($subCategories as $category) {
            $category->delete();
        }
    }

    /**
     * Overload for the position management.
     *
     * @param Base\ProductCategory $productCategory
     *                                              {@inheritdoc}
     */
    protected function doAddProductCategory($productCategory): void
    {
        parent::doAddProductCategory($productCategory);

        $productCategoryPosition = ProductCategoryQuery::create()
            ->filterByCategoryId($productCategory->getCategoryId())
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        $productCategory->setPosition($productCategoryPosition !== null ? $productCategoryPosition->getPosition() + 1 : 1);
    }
}
