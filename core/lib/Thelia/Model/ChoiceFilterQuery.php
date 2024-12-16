<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Model\Base\ChoiceFilterQuery as BaseChoiceFilterQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'choice_filter' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ChoiceFilterQuery extends BaseChoiceFilterQuery
{
    /**
     * @param int $templateId
     * @param string[] list of locale
     * @return Attribute[]|ObjectCollection
     */
    public static function findAttributesByTemplateId($templateId, $locales = ['en_US']): ObjectCollection|array
    {
        $attributeQuery = AttributeQuery::create();

        $attributeQuery->useAttributeTemplateQuery()
            ->filterByTemplateId($templateId)
            ->endUse();

        $attributeQuery->useAttributeI18nQuery(null, Criteria::LEFT_JOIN)
            ->endUse();

        $locales = array_map(function ($value) {
            return '"' . $value . '"';
        }, $locales);

        $attributeQuery->addJoinCondition('AttributeI18n', 'AttributeI18n.locale IN (' . implode(',', $locales) . ')');

        $attributeQuery->withColumn('AttributeI18n.title', 'Title');
        $attributeQuery->withColumn('AttributeTemplate.position', 'Position');

        $attributeQuery->groupBy('id');

        $attributeQuery->orderBy('position');

        return $attributeQuery->find();
    }

    /**
     * @param int $templateId
     * @param string[] list of locale
     * @return Feature[]|ObjectCollection
     */
    public static function findFeaturesByTemplateId($templateId, $locales = ['en_US']): ObjectCollection|array
    {
        $featureQuery = FeatureQuery::create();

        $featureQuery->useFeatureTemplateQuery()
            ->filterByTemplateId($templateId)
            ->endUse();

        $featureQuery->useFeatureI18nQuery(null, Criteria::LEFT_JOIN)
            ->endUse();

        $locales = array_map(function ($value) {
            return '"' . $value . '"';
        }, $locales);

        $featureQuery->addJoinCondition('FeatureI18n', 'FeatureI18n.locale IN (' . implode(',', $locales) . ')');

        $featureQuery->withColumn('FeatureI18n.title', 'Title');
        $featureQuery->withColumn('FeatureTemplate.position', 'Position');

        $featureQuery->groupBy('id');

        $featureQuery->orderBy('position');

        return $featureQuery->find();
    }

    /**
     * @param Category $category
     * @return Category[]
     */
    protected static function getParentCategoriesHasTemplate(Category $category): array
    {
        $categories = [];
        if (0 !== (int) $category->getParent()) {
            $category = CategoryQuery::create()->filterById($category->getParent())->findOne();

            if (null !== $category->getDefaultTemplateId()) {
                $categories[] = $category;
            }

            $categories += static::getParentCategoriesHasTemplate($category);
        }

        return $categories;
    }

    public static function findChoiceFilterByCategory(
        Category $category,
                 &$templateId = null,
                 &$categoryId = null
    ): array|ObjectCollection
    {
        $choiceFilters = self::create()
            ->filterByCategoryId($category->getId())
            ->orderByPosition()
            ->find();

        $parents = static::getParentCategoriesHasTemplate($category);

        if (count($choiceFilters)) {
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

            if (count($choiceFilters)) {
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

            if (count($choiceFilters)) {
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

            if (count($choiceFilters)) {
                $templateId = $parent->getDefaultTemplateId();
                $categoryId = null;
                return $choiceFilters;
            }
        }

        return new ObjectCollection();
    }
}
