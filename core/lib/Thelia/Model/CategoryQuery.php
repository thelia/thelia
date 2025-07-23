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
use Propel\Runtime\Exception\PropelException;
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
     */
    public static function countChild(int $parent): int
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
    public static function findAllChild(int|array $categoryId, int $depth = 0, int $currentPos = 0): array
    {
        $result = [];

        if (\is_array($categoryId)) {
            foreach ($categoryId as $categorySingleId) {
                $result = array_merge($result, (array) self::findAllChild($categorySingleId, $depth, $currentPos));
            }
        } else {
            ++$currentPos;

            if ($depth === $currentPos && 0 !== $depth) {
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
     *
     * @throws PropelException
     */
    public static function findAllChildId(int|array $categoryId, int $depth = 0, int $currentPos = 0): array
    {
        static $cache = [];

        $result = [];

        if (\is_array($categoryId)) {
            foreach ($categoryId as $categorySingleId) {
                $result = array_merge($result, self::findAllChildId($categorySingleId, $depth, $currentPos));
            }
        } elseif (!isset($cache[$categoryId])) {
            ++$currentPos;

            if ($depth === $currentPos && 0 !== $depth) {
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
    public static function getCategoryTreeIds(int|array $categoryId, int $depth = 1): array
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
                    self::getCategoryTreeIds($category->getId(), $depth - 1),
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
     * @return array An array of \Thelia\Model\Category from root to wanted category
     *               or an empty array if Category ID doesn't exists
     */
    public static function getPathToCategory(int $categoryId): array
    {
        $path = [];

        $category = (new self())->findPk($categoryId);

        if (null !== $category) {
            $path[] = $category;

            if (0 !== $category->getParent()) {
                $path = array_merge(self::getPathToCategory($category->getParent()), $path);
            }
        }

        return $path;
    }
}

// CategoryQuery
