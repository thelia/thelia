<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;

class TheliaFilter extends AbstractFilter
{

    public function __construct(private readonly FilterService $filterService)
    {
        parent::__construct();
    }

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $request = $context['request'] ?? null;
        if (!$request || count($request->get('tfilters', [])) < 1){
            return;
        }
        $this->filterService->filterWithTFilter(request: $request,query: $query);
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
