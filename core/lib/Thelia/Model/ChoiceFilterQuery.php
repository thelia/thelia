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
use Thelia\Model\Base\ChoiceFilterQuery as BaseChoiceFilterQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'choice_filter' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ChoiceFilterQuery extends BaseChoiceFilterQuery
{
    /**
     * @param string[] list of locale
     *
     * @return Attribute[]|ObjectCollection
     */
    public static function findAttributesByTemplateId(int $templateId, $locales = ['en_US']): ObjectCollection|array
    {
        $attributeQuery = AttributeQuery::create();

        $attributeQuery->useAttributeTemplateQuery()
            ->filterByTemplateId($templateId)
            ->endUse();

        $attributeQuery->useAttributeI18nQuery(null, Criteria::LEFT_JOIN)
            ->endUse();

        $locales = array_map(static fn ($value): string => '"'.$value.'"', $locales);

        $attributeQuery->addJoinCondition('AttributeI18n', 'AttributeI18n.locale IN ('.implode(',', $locales).')');

        $attributeQuery->withColumn('AttributeI18n.title', 'Title');
        $attributeQuery->withColumn('AttributeTemplate.position', 'Position');

        $attributeQuery->groupBy('id');

        $attributeQuery->orderBy('position');

        return $attributeQuery->find();
    }

    /**
     * @param string[] list of locale
     *
     * @return Feature[]|ObjectCollection
     */
    public static function findFeaturesByTemplateId(int $templateId, $locales = ['en_US']): ObjectCollection|array
    {
        $featureQuery = FeatureQuery::create();

        $featureQuery->useFeatureTemplateQuery()
            ->filterByTemplateId($templateId)
            ->endUse();

        $featureQuery->useFeatureI18nQuery(null, Criteria::LEFT_JOIN)
            ->endUse();

        $locales = array_map(static fn ($value): string => '"'.$value.'"', $locales);

        $featureQuery->addJoinCondition('FeatureI18n', 'FeatureI18n.locale IN ('.implode(',', $locales).')');

        $featureQuery->withColumn('FeatureI18n.title', 'Title');
        $featureQuery->withColumn('FeatureTemplate.position', 'Position');

        $featureQuery->groupBy('id');

        $featureQuery->orderBy('position');

        return $featureQuery->find();
    }

    /**
     * @return Category[]
     */
    protected static function getParentCategoriesHasTemplate(Category $category): array
    {
        $categories = [];

        if (0 !== (int) $category->getParent()) {
            $category = CategoryQuery::create()->filterById($category->getParent())->findOne();

            if (null === $category) {
                return $categories;
            }

            if (null !== $category?->getDefaultTemplateId()) {
                $categories[] = $category;
            }

            $categories += static::getParentCategoriesHasTemplate($category);
        }

        return $categories;
    }

    public static function findChoiceFilterByCategory(
        Category $category,
        &$templateId = null,
        &$categoryId = null,
    ): array|ObjectCollection {
        $choiceFilters = self::create()
            ->filterByCategoryId($category->getId())
            ->orderByPosition()
            ->find();

        $parents = static::getParentCategoriesHasTemplate($category);

        if (\count($choiceFilters) > 0) {
            if (null !== $category->getDefaultTemplateId()) {
                $templateId = $category->getDefaultTemplateId();
                $categoryId = $category->getId();

                return $choiceFilters;
            }

            foreach ($parents as $parent) {
                if (null !== $parent->getDefaultTemplateId()) {
                    $templateId = $parent->getDefaultTemplateId();
                    $categoryId = $category->getId();

                    return $choiceFilters;
                }
            }
        }

        if (null !== $category->getDefaultTemplateId()) {
            $choiceFilters = self::create()
                ->filterByCategoryId($category->getId())
                ->orderByPosition()
                ->find();

            if (\count($choiceFilters) > 0) {
                $templateId = $category->getDefaultTemplateId();
                $categoryId = $category->getId();

                return $choiceFilters;
            }
        }

        foreach ($parents as $parent) {
            $choiceFilters = self::create()
                ->filterByCategoryId($parent->getId())
                ->orderByPosition()
                ->find();

            if (\count($choiceFilters) > 0) {
                $templateId = $parent->getDefaultTemplateId();
                $categoryId = $parent->getId();

                return $choiceFilters;
            }
        }

        if (null !== $category->getDefaultTemplateId()) {
            $choiceFilters = self::create()
                ->filterByTemplateId($category->getDefaultTemplateId())
                ->orderByPosition()
                ->find();

            $templateId = $category->getDefaultTemplateId();
            $categoryId = null;

            return $choiceFilters;
        }

        foreach ($parents as $parent) {
            $choiceFilters = self::create()
                ->filterByTemplateId($parent->getDefaultTemplateId())
                ->orderByPosition()
                ->find();

            if (\count($choiceFilters) > 0) {
                $templateId = $parent->getDefaultTemplateId();
                $categoryId = null;

                return $choiceFilters;
            }
        }

        return new ObjectCollection();
    }

    /**
     * The filter column of a brand. Thelia configures filters per category, never per brand, so
     * the page of a brand borrows the columns of the categories where at least one of its
     * visible products is filed — each read the way that category's own page reads it, parent
     * template included — then deduplicated by what a row rules: a feature, an attribute, or
     * another criterion.
     *
     * Two categories can rule the same feature differently. The row kept is the visible one with
     * the lowest position: a brand page offers a filter as soon as one of its categories offers
     * it, and hides it only when none of them shows it.
     *
     * Every category is read in the same handful of queries rather than one after the other:
     * asking category by category cost three queries per category, on every render of the
     * column, for a page whose whole point is to gather many of them.
     *
     * @return array<ChoiceFilter>
     */
    public static function findChoiceFilterByBrand(Brand $brand, ?int &$templateId = null): array
    {
        $categoryIds = ProductCategoryQuery::create()
            ->useProductQuery()
                ->filterByBrandId($brand->getId())
                ->filterByVisible(1)
            ->endUse()
            ->select('CategoryId')
            ->distinct()
            ->find()
            ->getData();

        if ([] === $categoryIds) {
            return [];
        }

        $categories = CategoryQuery::create()
            ->filterById($categoryIds, Criteria::IN)
            ->orderById()
            ->find()
            ->getData();

        if ([] === $categories) {
            return [];
        }

        $ancestors = self::ancestorsHavingTemplate($categories);
        $read = array_merge($categories, ...array_values($ancestors));
        $rowsByCategory = self::choiceFiltersByCategory($read);
        $rowsByTemplate = self::choiceFiltersByTemplate($read);

        $choiceFilters = [];

        foreach ($categories as $category) {
            [$categoryChoiceFilters, $categoryTemplateId] = self::choiceFiltersOfCategory(
                category: $category,
                ancestors: $ancestors[(int) $category->getId()] ?? [],
                rowsByCategory: $rowsByCategory,
                rowsByTemplate: $rowsByTemplate,
            );

            $templateId ??= $categoryTemplateId;

            foreach ($categoryChoiceFilters as $choiceFilter) {
                $criterion = self::ruledCriterion($choiceFilter);
                $choiceFilters[$criterion] = self::preferredChoiceFilter($choiceFilters[$criterion] ?? null, $choiceFilter);
            }
        }

        $choiceFilters = array_values($choiceFilters);

        usort(
            $choiceFilters,
            static fn (ChoiceFilter $left, ChoiceFilter $right): int => (int) $left->getPosition() <=> (int) $right->getPosition(),
        );

        return $choiceFilters;
    }

    /**
     * What one category brings to the column, read from rows already in hand and in the order
     * {@see self::findChoiceFilterByCategory()} reads them: its own rows when it or an ancestor
     * carrying a template gives them one, else the own rows of the nearest ancestor that has
     * some, else the rows of its template, else the rows of the nearest ancestor's template
     * that has some.
     *
     * @param array<Category>                 $ancestors      the ancestors carrying a template, nearest first
     * @param array<int, array<ChoiceFilter>> $rowsByCategory
     * @param array<int, array<ChoiceFilter>> $rowsByTemplate
     *
     * @return array{0: array<ChoiceFilter>, 1: int|null}
     */
    private static function choiceFiltersOfCategory(
        Category $category,
        array $ancestors,
        array $rowsByCategory,
        array $rowsByTemplate,
    ): array {
        $categoryTemplateId = $category->getDefaultTemplateId();
        $own = $rowsByCategory[(int) $category->getId()] ?? [];

        if ([] !== $own) {
            if (null !== $categoryTemplateId) {
                return [$own, (int) $categoryTemplateId];
            }

            foreach ($ancestors as $ancestor) {
                if (null !== $ancestor->getDefaultTemplateId()) {
                    return [$own, (int) $ancestor->getDefaultTemplateId()];
                }
            }
        }

        foreach ($ancestors as $ancestor) {
            $ancestorRows = $rowsByCategory[(int) $ancestor->getId()] ?? [];

            if ([] !== $ancestorRows) {
                return [$ancestorRows, (int) $ancestor->getDefaultTemplateId()];
            }
        }

        if (null !== $categoryTemplateId) {
            return [$rowsByTemplate[(int) $categoryTemplateId] ?? [], (int) $categoryTemplateId];
        }

        foreach ($ancestors as $ancestor) {
            $templateRows = $rowsByTemplate[(int) $ancestor->getDefaultTemplateId()] ?? [];

            if ([] !== $templateRows) {
                return [$templateRows, (int) $ancestor->getDefaultTemplateId()];
            }
        }

        return [[], null];
    }

    /**
     * Every ancestor carrying a default template, nearest first, for every category at once:
     * one query per level of the tree, where climbing category by category costs one per
     * category and per level. The climb goes on past the first match, the way
     * {@see self::getParentCategoriesHasTemplate()} does for a single category: a nearer
     * ancestor may carry a template and still rule no row, while a further one does.
     *
     * @param array<Category> $categories
     *
     * @return array<int, array<Category>> category id => its ancestors carrying a template, nearest first
     */
    private static function ancestorsHavingTemplate(array $categories): array
    {
        $found = [];
        $climbing = [];
        $seen = [];

        foreach ($categories as $category) {
            $parentId = (int) $category->getParent();

            if (0 !== $parentId) {
                $climbing[(int) $category->getId()] = $parentId;
                $seen[(int) $category->getId()] = [$parentId => true];
            }
        }

        while ([] !== $climbing) {
            $parentsById = [];

            foreach (CategoryQuery::create()->filterById(array_values(array_unique($climbing)), Criteria::IN)->find() as $parent) {
                $parentsById[(int) $parent->getId()] = $parent;
            }

            $next = [];

            foreach ($climbing as $categoryId => $parentId) {
                $parent = $parentsById[$parentId] ?? null;

                if (null === $parent) {
                    continue;
                }

                if (null !== $parent->getDefaultTemplateId()) {
                    $found[$categoryId][] = $parent;
                }

                $grandParentId = (int) $parent->getParent();

                // A tree whose branch loops back on itself would climb forever.
                if (0 === $grandParentId || isset($seen[$categoryId][$grandParentId])) {
                    continue;
                }

                $seen[$categoryId][$grandParentId] = true;
                $next[$categoryId] = $grandParentId;
            }

            $climbing = $next;
        }

        return $found;
    }

    /**
     * @param array<Category> $categories
     *
     * @return array<int, array<ChoiceFilter>> category id => the rows it rules itself, by position
     */
    private static function choiceFiltersByCategory(array $categories): array
    {
        $categoryIds = self::identifiersOf($categories);

        if ([] === $categoryIds) {
            return [];
        }

        $rows = [];

        foreach (self::create()->filterByCategoryId($categoryIds, Criteria::IN)->orderByPosition()->find() as $choiceFilter) {
            $rows[(int) $choiceFilter->getCategoryId()][] = $choiceFilter;
        }

        return $rows;
    }

    /**
     * @param array<Category> $categories
     *
     * @return array<int, array<ChoiceFilter>> template id => every row it rules, by position
     */
    private static function choiceFiltersByTemplate(array $categories): array
    {
        $templateIds = [];

        foreach ($categories as $category) {
            if (null !== $category->getDefaultTemplateId()) {
                $templateIds[] = (int) $category->getDefaultTemplateId();
            }
        }

        $templateIds = array_values(array_unique($templateIds));

        if ([] === $templateIds) {
            return [];
        }

        $rows = [];

        foreach (self::create()->filterByTemplateId($templateIds, Criteria::IN)->orderByPosition()->find() as $choiceFilter) {
            $rows[(int) $choiceFilter->getTemplateId()][] = $choiceFilter;
        }

        return $rows;
    }

    /**
     * @param array<Category> $categories
     *
     * @return array<int>
     */
    private static function identifiersOf(array $categories): array
    {
        return array_values(array_unique(array_map(
            static fn (Category $category): int => (int) $category->getId(),
            $categories,
        )));
    }

    /**
     * What a row rules, as a key two rows of two categories can be compared on.
     */
    private static function ruledCriterion(ChoiceFilter $choiceFilter): string
    {
        return match (true) {
            null !== $choiceFilter->getFeatureId() => 'feature-'.$choiceFilter->getFeatureId(),
            null !== $choiceFilter->getAttributeId() => 'attribute-'.$choiceFilter->getAttributeId(),
            null !== $choiceFilter->getOtherId() => 'other-'.$choiceFilter->getOtherId(),
            default => 'row-'.$choiceFilter->getId(),
        };
    }

    private static function preferredChoiceFilter(?ChoiceFilter $kept, ChoiceFilter $candidate): ChoiceFilter
    {
        if (!$kept instanceof ChoiceFilter) {
            return $candidate;
        }

        if ((bool) $kept->isVisible() !== (bool) $candidate->isVisible()) {
            return $candidate->isVisible() ? $candidate : $kept;
        }

        return (int) $candidate->getPosition() < (int) $kept->getPosition() ? $candidate : $kept;
    }
}
