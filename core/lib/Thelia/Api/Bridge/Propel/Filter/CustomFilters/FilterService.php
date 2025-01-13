<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\CategoryFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

readonly class FilterService
{
    public function __construct(
        #[TaggedIterator('api.thelia.filter')]
        private readonly iterable             $filters,
    )
    {
    }

    private function getAvailableFiltersWithTFilter(string $resourceType, array $tfilters): array
    {
        $filters = $this->getAvailableFilters($resourceType);
        $filterResult = [];
        foreach ($filters as $filter) {
            foreach ($tfilters as $tfilter => $tfilterValue) {
                if (in_array($tfilter, $filter->getFilterName(), true)) {
                    $filterResult [] = [
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
            if (in_array($resourceType, $filter->getResourceType(), true)) {
                $filters [] = $filter;
            }
        }
        return $filters;
    }

    public function filterWithTFilter($request, ?array $uriVariables = [],?array $context = null,?ModelCriteria $query = null,?bool &$isCategoryFilter = false): iterable
    {
        $tfilters = null;
        if (isset($context['path_info'])){
            $pathInfo = $context['path_info'];
        }
        if ($request){
            $tfilters = $request->get('tfilters', []);
            $pathInfo = $request->getPathInfo();
        }
        $resource = $uriVariables['resource'] ?? null;
        if (!$tfilters && isset($context["filters"]["tfilters"])){
            $tfilters = $context["filters"]["tfilters"];
        }
        if (!$resource && $pathInfo){
            $segments = explode('/', $pathInfo);
            $resource = end($segments);
        }
        $filters = $this->getAvailableFiltersWithTFilter($resource, $tfilters);

        if (!$query){
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
            if ($filterClass instanceof CategoryFilter){
                $isCategoryFilter = true;
            }
            if (is_string($value)){
                $value = explode(',',$value);
            }
            $filterClass->filter($query, $value);
        }
        return $query->groupById();
    }
}
