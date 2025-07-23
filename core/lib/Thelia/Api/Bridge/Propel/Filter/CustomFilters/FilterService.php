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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\CategoryFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Resource\Filter;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\ChoiceFilterQuery;
use Thelia\Service\Model\LangService;

readonly class FilterService
{
    public function __construct(
        #[TaggedIterator('api.thelia.filter')]
        private readonly iterable $filters,
        private readonly LangService $langService,
        private readonly RequestStack $requestStack,
        private readonly Translator $translator,
    ) {
    }

    private function getAvailableFiltersWithTFilter(string $resourceType, array $tfilters): array
    {
        $filters = $this->getAvailableFilters($resourceType);
        $filterResult = [];

        foreach ($filters as $filter) {
            foreach ($tfilters as $tfilter => $tfilterValue) {
                if (\in_array($tfilter, $filter->getFilterName(), true)) {
                    $filterResult[] = [
                        'filter' => $filter,
                        'tfilter' => $tfilter,
                        'value' => $tfilterValue,
                        'resourceType' => $resourceType,
                    ];
                }
            }
        }

        return $filterResult;
    }

    public function getAvailableFilters(string $resourceType): array
    {
        $filters = [];

        foreach ($this->filters as $filter) {
            if (\in_array($resourceType, $filter->getResourceType(), true)) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    public function filterTFilterWithRequest($request, ?ModelCriteria $query = null): iterable
    {
        $tfilters = $request->get('tfilters', []);
        $categoryDepth = $request->get(CategoryFilter::CATEGORY_DEPTH_NAME, null);
        $pathInfo = $request->getPathInfo();
        $segments = explode('/', (string) $pathInfo);
        $resource = end($segments);

        return $this->filterWithTFilter(tfilters: $tfilters, resource: $resource, query: $query, categoryDepth: $categoryDepth);
    }

    public function filterTFilterWithContext(?array $context = null, ?ModelCriteria $query = null): iterable
    {
        $tfilters = $context['filters']['tfilters'] ?? [];
        $categoryDepth = $context['filters'][CategoryFilter::CATEGORY_DEPTH_NAME] ?? null;
        $pathInfo = $context['path_info'];
        $segments = explode('/', (string) $pathInfo);
        $resource = end($segments);

        return $this->filterWithTFilter(tfilters: $tfilters, resource: $resource, query: $query, categoryDepth: $categoryDepth);
    }

    public function filterWithTFilter(array $tfilters, string $resource, ?ModelCriteria $query = null, ?int $categoryDepth = null): iterable
    {
        $filters = $this->getAvailableFiltersWithTFilter($resource, $tfilters);

        if (!$query instanceof ModelCriteria) {
            $queryClass = 'Thelia\\Model\\'.ucfirst($resource).'Query';

            if (!class_exists($queryClass)) {
                $queryClass = 'Thelia\\Model\\'.ucfirst(mb_substr($resource, 0, -1)).'Query';
            }

            if (!class_exists($queryClass)) {
                throw new \RuntimeException('Not found class: '.$queryClass);
            }

            $query = $queryClass::create();
        }

        foreach ($filters as $filter) {
            $filterClass = $filter['filter'];
            $values = $filter['value'];

            if (!$filterClass instanceof TheliaFilterInterface) {
                throw new \RuntimeException(\sprintf('The "%s" filter must implements TheliaFilterInterface.', $filterClass::class));
            }

            if (\is_string($values)) {
                $values = explode(',', $values);
            }

            if (\is_array($values)) {
                $values = array_map(static fn ($value): int => (int) $value, $values);
            }

            if ($filterClass instanceof CategoryFilter) {
                $filterClass->filter($query, $values, $categoryDepth);
            } else {
                $filterClass->filter($query, $values);
            }
        }

        return $query->groupById();
    }

    public function getFilters(array $context, string $resource): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            throw new \InvalidArgumentException('The request is required.');
        }

        $isApiRoute = $request->get('isApiRoute', false);

        if ($isApiRoute) {
            $tfilters = $request->get('tfilters', []);
            $query = $this->filterTFilterWithRequest(request: $request);
        } else {
            $tfilters = $context['filters']['tfilters'] ?? [];
            $query = $this->filterTFilterWithContext(context: $context);
        }

        $filterObjects = [];
        $locale = $context['filters']['locale'] ?? $request->get('locale');
        $locale ??= $this->langService->getLocale();
        $objects = $query->find();
        $filters = $this->getAvailableFilters($resource);

        foreach ($filters as $filter) {
            $values = [];
            $item = null;

            foreach ($objects as $item) {
                if ($filter instanceof CategoryFilter) {
                    $categoryId = $this->retrieveFilterValue(
                        theliaFilterNames: CategoryFilter::getFilterName(),
                        tfilters: $tfilters,
                    );
                    $depth = $tfilters[CategoryFilter::CATEGORY_DEPTH_NAME] ?? 1;
                    $values = $filter->getValue(
                        activeRecord: $item,
                        locale: $locale,
                        valueSearched: $categoryId,
                        depth : $depth,
                    );
                    break;
                }

                $possibleValues = $filter->getValue(
                    activeRecord: $item,
                    locale: $locale,
                );

                if (!$possibleValues) {
                    continue;
                }

                foreach ($possibleValues as $value) {
                    $values[] = $value;
                }
            }

            $id = null;
            $isVisible = true;
            $position = null;
            $this->choiceFiltersManagement(
                tfilters: $tfilters,
                filter: $filter,
                values: $values,
                isVisible: $isVisible,
                position: $position,
            );

            $hasMainResource = (isset($values[0]['mainId'], $values[0]['mainTitle']));

            if ($hasMainResource) {
                $values = array_intersect_key($values, array_unique(array_map(
                    static fn ($item): string => $item['id'].'-'.$item['mainId'],
                    $values,
                )));

                $splitValues = [];

                foreach ($values as $value) {
                    $splitValues[$value['mainId']][] = $value;
                }

                foreach ($splitValues as $value) {
                    if (isset($value[0]['visible']) && $value[0]['position']) {
                        $position = $value[0]['position'];
                        $isVisible = $value[0]['visible'];
                        $value = array_map(static function (array $val): array {
                            unset($val['visible'], $val['position']);

                            return $val;
                        }, $value);
                    }

                    if (!$isVisible) {
                        continue;
                    }

                    $title = $value[0]['mainTitle'] ?? '';

                    if ($filter instanceof CategoryFilter) {
                        $title = $this->translator->trans(id: 'Category', locale: $locale);
                    }

                    $filterDto = new Filter();
                    $filterDto
                        ->setId($value[0]['mainId'] ?? null)
                        ->setTitle($title)
                        ->setType($filter::getFilterName()[0])
                        ->setInputType('checkbox')
                        ->setPosition($position);

                    $value = array_map(static function (array $val): array {
                        unset($val['mainId'], $val['mainTitle']);

                        return $val;
                    }, $value);

                    $filterDto->setValues($value);
                    $filterObjects[] = $filterDto;
                }
            }

            if (!$hasMainResource && [] !== $values) {
                $values = array_intersect_key($values, array_unique(array_column($values, 'id')));

                if (!$isVisible) {
                    continue;
                }

                $filterObjects[] = (new Filter())
                    ->setId($id)
                    ->setTitle($filter::getFilterName()[0] ?? '')
                    ->setType($filter::getFilterName()[0] ?? '')
                    ->setInputType('checkbox')
                    ->setPosition($position)
                    ->setValues(array_values($values));
            }
        }

        foreach ($filterObjects as $filterObject) {
            if (null === $filterObject->getPosition()) {
                $allPosition = array_map(static fn ($filterObject): ?int => $filterObject->getPosition(), $filterObjects);
                $max = max($allPosition);
                $filterObject->setPosition($max + 1);
            }
        }

        usort($filterObjects, static fn ($a, $b): int => $a->getPosition() <=> $b->getPosition());

        return $filterObjects;
    }

    private function retrieveFilterValue(array $theliaFilterNames, array $tfilters): string|array|null
    {
        $ids = null;

        foreach ($theliaFilterNames as $filterName) {
            if (!isset($tfilters[$filterName])) {
                continue;
            }

            $ids = $tfilters[$filterName];
        }

        return $ids;
    }

    private function choiceFiltersManagement(array $tfilters, TheliaFilterInterface $filter, array &$values, bool &$isVisible, ?int &$position): void
    {
        if (!$this->hasFilter(theliaFilterNames: CategoryFilter::getFilterName(), tfilters: $tfilters)) {
            return;
        }

        $categoryId = $this->retrieveFilterValue(theliaFilterNames: CategoryFilter::getFilterName(), tfilters: $tfilters);
        $category = CategoryQuery::create()->findPk(key: $categoryId);
        $choiceFiltersCategory = ChoiceFilterQuery::findChoiceFilterByCategory(category: $category, templateId: $templateIdFind)->getData();
        $choiceFiltersTemplate = [];

        if ($templateIdFind) {
            $choiceFiltersTemplate = ChoiceFilterQuery::create()->filterByTemplateId($templateIdFind)->find()->getData();
        }

        $choiceFilters = $choiceFiltersCategory;

        if (empty($choiceFilters)) {
            $choiceFilters = $choiceFiltersTemplate;
        }

        /** @var ChoiceFilter $choiceFilter */
        foreach ($choiceFilters as $choiceFilter) {
            $otherType = $choiceFilter->getChoiceFilterOther()?->getType();

            if (\in_array($otherType, $filter->getFilterName(), true)) {
                $isVisible = (bool) $choiceFilter->isVisible();
                $position = $choiceFilter->getPosition();

                return;
            }

            foreach ($values as $index => $value) {
                if ($filter instanceof TheliaChoiceFilterInterface) {
                    $mainType = $filter->getChoiceFilterType();

                    if ($choiceFilter->getAttribute() instanceof $mainType && $choiceFilter->getAttribute()->getId() === $value['mainId']) {
                        $values[$index]['visible'] = (bool) $choiceFilter->isVisible();
                        $values[$index]['position'] = $choiceFilter->getPosition();
                    }

                    if ($choiceFilter->getFeature() instanceof $mainType && $choiceFilter->getFeature()->getId() === $value['mainId']) {
                        $values[$index]['visible'] = (bool) $choiceFilter->isVisible();
                        $values[$index]['position'] = $choiceFilter->getPosition();
                    }
                }
            }
        }
    }

    private function hasFilter(array $theliaFilterNames, array $tfilters): bool
    {
        return !\in_array($this->retrieveFilterValue($theliaFilterNames, $tfilters), ['', '0', null], true)
            && [] !== $this->retrieveFilterValue($theliaFilterNames, $tfilters);
    }

    public function getCategoriesRecursively($categoryId, int $maxDepth, array $categoriesFound = [], int $depth = 1): array
    {
        $categories = CategoryQuery::create()->filterByParent($categoryId)->find();

        if ($depth > $maxDepth) {
            return $categoriesFound;
        }

        foreach ($categories as $category) {
            if (!$category->getVisible()) {
                continue;
            }

            $categoriesFound[$depth][] = $category;
            $categoriesFound = $this->getCategoriesRecursively(
                categoryId: $category->getId(),
                maxDepth: $maxDepth,
                categoriesFound: $categoriesFound,
                depth: $depth + 1,
            );
        }

        return $categoriesFound;
    }
}
