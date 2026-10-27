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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FileModelParentInterface;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Model\Base\Category as BaseCategory;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;

class Category extends BaseCategory implements FileModelParentInterface
{
    use PositionManagementTrait;
    use UrlRewritingTrait;

    /**
     * @return int number of child for the current category
     */
    public function countChild(): int
    {
        return CategoryQuery::countChild($this->getId());
    }

    public function getRewrittenUrlViewName()
    {
        return 'category';
    }

    /**
     * count all products for current category and sub categories.
     *
     * /!\ the number of queries is exponential, use it with caution
     *
     * @param ProductVisibility|null $visibleToVisitor  when given, the products the current visitor may not see
     *                                                  (reserved operations, restricted categories, module rules) are not counted
     * @param list<int>              $closedCategoryIds the sub-categories the visitor may not browse: a product they hold
     *                                                  is not counted for them, even when another category keeps it visible
     */
    public function countAllProducts($productVisibility = true, ?ProductVisibility $visibleToVisitor = null, array $closedCategoryIds = []): int
    {
        $children = CategoryQuery::findAllChild($this->getId());
        $children[] = $this;

        if ([] !== $closedCategoryIds) {
            $children = array_values(array_filter($children, static fn (Category $child): bool => !\in_array((int) $child->getId(), $closedCategoryIds, true)));
        }

        $query = ProductQuery::create();

        if ('*' !== $productVisibility) {
            $query->filterByVisible($productVisibility);
        }

        // A product filed in two categories of the sub-tree is one product.
        $query
            ->distinct()
            ->useProductCategoryQuery()
            ->filterByCategory(new ObjectCollection($children), Criteria::IN)
            ->endUse();

        $visibleToVisitor?->applyTo($query, ProductTableMap::COL_ID);

        return $query->count();
    }

    /**
     * count visible products only for current category and sub categories.
     *
     * /!\ the number of queries is exponential, use it with caution
     */
    public function countAllProductsVisibleOnly(): int
    {
        return $this->countAllProducts(true);
    }

    /**
     * Get the root category.
     */
    public function getRoot(int $categoryId)
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
     */
    protected function addCriteriaToPositionQuery(CategoryQuery $query): void
    {
        $query->filterByParent($this->getParent());
    }

    public function deleteProducts(?ConnectionInterface $con = null): void
    {
        $productsCategories = ProductCategoryQuery::create()
            ->filterByCategoryId($this->getId())
            ->filterByDefaultCategory(1)
            ->find($con);

        if (
            $con instanceof ConnectionInterface
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

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        $this->setPosition($this->getNextPosition());

        parent::preInsert($con);

        return true;
    }

    public function preDelete(?ConnectionInterface $con = null): bool
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                'parent' => $this->getParent(),
            ],
        );
        $this->deleteProducts($con);

        return true;
    }

    public function postDelete(?ConnectionInterface $con = null): void
    {
        parent::postDelete($con);

        $this->markRewrittenUrlObsolete();

        // delete all subcategories
        $subCategories = CategoryQuery::findAllChild($this->getId());

        foreach ($subCategories as $category) {
            $category->delete();
        }
    }

    /**
     * Overload for the position management.
     */
    protected function doAddProductCategory(Base\ProductCategory $productCategory): void
    {
        parent::doAddProductCategory($productCategory);

        // Propel associates a link every time it hydrates one, not only when a
        // link is being created: the join that reads a category with its
        // products lands here once per row. Numbering there cost a query per row
        // and replaced the position the row carries with a number nothing writes
        // back.
        if (!$productCategory->isNew()) {
            return;
        }

        $productCategoryPosition = ProductCategoryQuery::create()
            ->filterByCategoryId($productCategory->getCategoryId())
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        $productCategory->setPosition(null !== $productCategoryPosition ? $productCategoryPosition->getPosition() + 1 : 1);
    }
}
