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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\CategoryFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Resource\Filter;
use Thelia\Model\ChoiceFilterQuery;
use Thelia\Service\Model\LangService;

readonly class FilterService
{
    public function __construct(
        #[TaggedIterator('api.thelia.filter')]
        private readonly iterable     $filters,
        private readonly LangService  $langService,
        private readonly RequestStack $requestStack,
    )
    {
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

    public function filterTFilterWithRequest($request, ModelCriteria $query = null): iterable
    {
        $tfilters = $request->get('tfilters', []);
        $pathInfo = $request->getPathInfo();
        $segments = explode('/', $pathInfo);
        $resource = end($segments);

        return $this->filterWithTFilter(tfilters: $tfilters, resource: $resource, query: $query);
    }

    public function filterTFilterWithContext(array $context = null, ModelCriteria $query = null): iterable
    {
        $tfilters = $context['filters']['tfilters'] ?? [];
        $pathInfo = $context['path_info'];
        $segments = explode('/', $pathInfo);
        $resource = end($segments);

        return $this->filterWithTFilter(tfilters: $tfilters, resource: $resource, query: $query);
    }

    public function filterWithTFilter(array $tfilters, string $resource, ModelCriteria $query = null): iterable
    {
        $filters = $this->getAvailableFiltersWithTFilter($resource, $tfilters);
        if (!$query) {
            $queryClass = "Thelia\Model\\" . ucfirst($resource) . 'Query';
            if (!class_exists($queryClass)) {
                $queryClass = "Thelia\Model\\" . ucfirst(mb_substr($resource, 0, -1)) . 'Query';
            }
            if (!class_exists($queryClass)) {
                throw new \RuntimeException('Not found class: ' . $queryClass);
            }
            $query = $queryClass::create();
        }
        foreach ($filters as $filter) {
            $filterClass = $filter['filter'];
            $value = $filter['value'];
            if (!$filterClass instanceof TheliaFilterInterface) {
                throw new \RuntimeException(sprintf('The "%s" filter must implements TheliaFilterInterface.', $filterClass::class));
            }
            if (\is_string($value)) {
                $value = explode(',', $value);
            }
            $filterClass->filter($query, $value);
        }

        return $query->groupById();
    }

    public function getFilters(array $context, string $resource): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
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
        $locale = $locale ?? $this->langService->getLocale();
        $objects = $query->find();
        $filters = $this->getAvailableFilters($resource);
        foreach ($filters as $filter) {
            $values = [];
            $item = null;
            foreach ($objects as $item) {
                if ($filter instanceof CategoryFilter) {
                    $categoryId = $this->retrieveFilterValue(
                        theliaFilterNames: CategoryFilter::getFilterName(),
                        tfilters: $tfilters
                    );
                    $depth = $tfilters[CategoryFilter::CATEGORY_DEPTH_NAME] ?? 1;
                    $values = $filter->getValue(
                        activeRecord: $item,
                        locale: $locale,
                        valueSearched: $categoryId,
                        depth : $depth
                    );
                    break;
                }
                $possibleValues = $filter->getValue(
                    activeRecord: $item,
                    locale: $locale
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
                item: $item,
                isVisible: $isVisible,
                position: $position,
                id: $id
            );

            $isTheliaChoiceFilter = ($filter instanceof TheliaChoiceFilterInterface);

            if ($isTheliaChoiceFilter) {
                $values = array_intersect_key($values, array_unique(array_map(
                    static function ($item) {
                        return $item['id'] . '-' . $item['mainId'];
                    },
                    $values
                )));

                $splitValues = [];
                foreach ($values as $value) {
                    $splitValues[$value['mainId']][] = $value;
                }
                foreach ($splitValues as $value) {
                    $filterDto = new Filter();
                    $filterDto
                        ->setId($value[0]['mainId'])
                        ->setTitle($value[0]['mainTitle'])
                        ->setType($filter::getFilterName()[0])
                        ->setInputType('checkbox')
                        ->setPosition($position)
                        ->setVisible($isVisible);

                    $value = array_map(static function ($val) {
                        unset($val['mainId'], $val['mainTitle']);
                        return $val;
                    }, $value);

                    $filterDto->setValues($value);
                    $filterObjects[] = $filterDto;
                }
            }

            if (!$isTheliaChoiceFilter) {
                $values = array_intersect_key($values, array_unique(array_column($values, 'id')));

                $filterObjects[] = (new Filter())
                    ->setId($id)
                    ->setTitle($filter::getFilterName()[0])
                    ->setType($filter::getFilterName()[0])
                    ->setInputType('checkbox')
                    ->setPosition($position)
                    ->setVisible($isVisible)
                    ->setValues(array_values($values));
            }
        }

        foreach ($filterObjects as $filterObject) {
            if ($filterObject->getPosition() === null) {
                $allPosition = array_map(static function ($filterObject) {
                    return $filterObject->getPosition();
                }, $filterObjects);
                $max = max($allPosition);
                $filterObject->setPosition($max + 1);
            }
        }

        usort($filterObjects, static function ($a, $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

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

    private function isFilterRequested(array $theliaFilterNames, array $tfilters): bool
    {
        return empty($this->retrieveFilterValue($theliaFilterNames, $tfilters));
    }

    private function choiceFiltersManagement(array $tfilters, $filter, mixed $item, bool &$isVisible, ?int &$position, ?int &$id): void
    {
        $choiceFilters = [];
        if ($this->isFilterRequested(theliaFilterNames: CategoryFilter::getFilterName(), tfilters: $tfilters)) {
            $categoryId = $this->retrieveFilterValue(theliaFilterNames: CategoryFilter::getFilterName(), tfilters: $tfilters);
            if ($categoryId) {
                $choiceFilters = ChoiceFilterQuery::create()->filterByCategoryId($categoryId)->find()->getData();
            }
            foreach ($choiceFilters as $choiceFilter) {
                $otherType = $choiceFilter->getChoiceFilterOther()?->getType();
                if (\in_array($otherType, $filter->getFilterName(), true)) {
                    $isVisible = $choiceFilter->isVisible();
                    $position = $choiceFilter->getPosition();
                }
                if ($filter instanceof TheliaChoiceFilterInterface && !empty($item)) {
                    $mainType = $filter->getChoiceFilterType($item);
                    $id = $mainType->getId();
                    if ($choiceFilter->getAttribute() instanceof $mainType && $choiceFilter->getAttribute()->getId() === $mainType->getId()) {
                        $isVisible = $choiceFilter->isVisible();
                        $position = $choiceFilter->getPosition();
                    }
                    if ($choiceFilter->getFeature() instanceof $mainType && $choiceFilter->getFeature()->getId() === $mainType->getId()) {
                        $isVisible = $choiceFilter->isVisible();
                        $position = $choiceFilter->getPosition();
                    }
                }
            }
        }
    }
}
