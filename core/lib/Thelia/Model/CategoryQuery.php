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

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\CategoryQuery as BaseCategoryQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'category' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class CategoryQuery extends BaseCategoryQuery
{
    /**
     * count how many direct children have a category.
     *
     * @param int $parent category parent id
     *
     * @return int
     */
    public static function countChild($parent)
    {
        return self::create()->filterByParent($parent)->count();
    }

    /**
     * find all category children for a given category. an array of \Thelia\Model\Category is return.
     *
     * @param int|int[] $categoryId the category id or an array of id
     * @param int       $depth      max depth you want to search
     * @param int       $currentPos don't change this param, it is used for recursion
     *
     * @return Category[]
     */
    public static function findAllChild($categoryId, $depth = 0, $currentPos = 0)
    {
        $result = [];

        if (\is_array($categoryId)) {
            foreach ($categoryId as $categorySingleId) {
                $result = array_merge($result, (array) self::findAllChild($categorySingleId, $depth, $currentPos));
            }
        } else {
            ++$currentPos;

            if ($depth == $currentPos && $depth != 0) {
                return [];
            }

            $categories = self::create()
                ->filterByParent($categoryId)
                ->find();

            foreach ($categories as $category) {
                $result[] = $category;
                $result = array_merge($result, (array) self::findAllChild($category->getId(), $depth, $currentPos));
            }
        }

        return $result;
    }

    /**
     * Find all IDs of child categories of a given category.
     *
     * @param int|int[] $categoryId
     * @param int       $depth
     * @param int       $currentPos
     *
     * @throws PropelException
     *
     * @return array
     */
    public static function findAllChildId($categoryId, $depth = 0, $currentPos = 0)
    {
        static $cache = [];

        $result = [];

        if (\is_array($categoryId)) {
            foreach ($categoryId as $categorySingleId) {
                $result = array_merge($result, self::findAllChildId($categorySingleId, $depth, $currentPos));
            }
        } elseif (!isset($cache[$categoryId])) {
            ++$currentPos;
            if ($depth == $currentPos && $depth != 0) {
                return [];
            }

            $subCategories = self::create()
                ->filterByParent($categoryId)
                ->select(['id'])
                ->find()
                ->getData();
            foreach ($subCategories as $subCategoryId) {
                $result[] = $subCategoryId;
                $result = array_merge($result, self::findAllChildId($subCategoryId, $depth, $currentPos));
            }

            $cache[$categoryId] = $result;
        } else {
            $result = $cache[$categoryId];
        }

        return $result;
    }

    /**
     * Return all category IDs of a category tree, starting at $categoryId, up to a depth of $depth.
     *
     * @param int|int[] $categoryId the category id or an array of category ids
     * @param int       $depth      max tree traversal depth
     *
     * @return int[]
     */
    public static function getCategoryTreeIds($categoryId, $depth = 1)
    {
        $result = \is_array($categoryId) ? $categoryId : [$categoryId];

        if ($depth > 1) {
            $categories = self::create()
                ->filterByParent($categoryId, Criteria::IN)
                ->withColumn('id')
                ->find();

            foreach ($categories as $category) {
                $result = array_merge(
                    $result,
                    self::getCategoryTreeIds($category->getId(), $depth - 1)
                );
            }
        }

        return $result;
    }

    /**
     * Get categories from root to child.
     *
     * @param int $categoryId Category ID
     *
     * @since 2.3.0
     *
     * @return array An array of \Thelia\Model\Category from root to wanted category
     *               or an empty array if Category ID doesn't exists
     */
    public static function getPathToCategory($categoryId)
    {
        $path = [];

        $category = (new self())->findPk($categoryId);
        if ($category !== null) {
            $path[] = $category;

            if ($category->getParent() !== 0) {
                $path = array_merge(self::getPathToCategory($category->getParent()), $path);
            }
        }

        return $path;
    }
}

// CategoryQuery
