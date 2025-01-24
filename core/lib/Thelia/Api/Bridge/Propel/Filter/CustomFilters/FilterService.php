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
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\CategoryFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

readonly class FilterService
{
    public function __construct(
        #[TaggedIterator('api.thelia.filter')]
        private readonly iterable $filters,
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

    public function filterTFilterWithRequest($request, ?bool &$isCategoryFilter = false, ModelCriteria $query = null): iterable
    {
        $tfilters = $request->get('tfilters', []);
        $pathInfo = $request->getPathInfo();
        $segments = explode('/', $pathInfo);
        $resource = end($segments);

        return $this->filterWithTFilter(tfilters: $tfilters, resource: $resource, query: $query, isCategoryFilter: $isCategoryFilter);
    }

    public function filterTFilterWithContext(array $context = null, ?bool &$isCategoryFilter = false, ModelCriteria $query = null): iterable
    {
        $tfilters = $context['filters']['tfilters'] ?? [];
        $pathInfo = $context['path_info'];
        $segments = explode('/', $pathInfo);
        $resource = end($segments);

        return $this->filterWithTFilter(tfilters: $tfilters, resource: $resource, query: $query, isCategoryFilter: $isCategoryFilter);
    }

    public function filterWithTFilter(array $tfilters, string $resource, ModelCriteria $query = null, ?bool &$isCategoryFilter = false): iterable
    {
        $filters = $this->getAvailableFiltersWithTFilter($resource, $tfilters);
        if (!$query) {
            $queryClass = "Thelia\Model\\".ucfirst($resource).'Query';
            if (!class_exists($queryClass)) {
                $queryClass = "Thelia\Model\\".ucfirst(mb_substr($resource, 0, -1)).'Query';
            }
            if (!class_exists($queryClass)) {
                throw new \RuntimeException('Not found class: '.$queryClass);
            }
            $query = $queryClass::create();
        }
        foreach ($filters as $filter) {
            $filterClass = $filter['filter'];
            $value = $filter['value'];
            if (!$filterClass instanceof TheliaFilterInterface) {
                throw new \RuntimeException(sprintf('The "%s" filter must implements TheliaFilterInterface.', $filterClass::class));
            }
            if ($filterClass instanceof CategoryFilter) {
                $isCategoryFilter = true;
            }
            if (\is_string($value)) {
                $value = explode(',', $value);
            }
            $filterClass->filter($query, $value);
        }

        return $query->groupById();
    }
}
