<?php

namespace Thelia\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\TheliaFilterInterface;
use Thelia\Api\Resource\Filter;

class TFiltersProvider implements ProviderInterface
{

    public function __construct(protected FilterService $filterService)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $resource = $uriVariables['resource'] ?? null;

        if (!$resource) {
            throw new \InvalidArgumentException('The "resource" parameter is required.');
        }
        $query = $this->filterService->filterWithTFilter(request: $context['request']);

        $filterObject = [];

        $objects = $query->find();
        $filters = $this->filterService->getAvailableFilters($resource);
        foreach ($filters as $filter) {
            $values = [];
            foreach ($objects as $item) {
                foreach ($filter->getValue($item) as $value) {
                    $values [] = $value;
                }
            }
            $values = array_intersect_key($values, array_unique(array_column($values, 'id')));
            $filterObject[] = (new Filter())
                ->setId(1)
                ->setTitle($filter->getFilterName()[0])
                ->setType($filter->getFilterName()[0])
                ->setInputType('checkbox')
                ->setVisible(true)
                ->setValues($values);
        }
        return $filterObject;
    }
}
