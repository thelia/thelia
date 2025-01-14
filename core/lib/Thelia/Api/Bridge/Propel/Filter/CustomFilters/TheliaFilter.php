<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;

class TheliaFilter extends AbstractFilter
{

    public function __construct(private readonly FilterService $filterService, private RequestStack $requestStack)
    {
        parent::__construct();
    }

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $request = $context['request'] ?? null;
        if (!$request){
            $request = $this->requestStack->getCurrentRequest();
        }
        if ((!$request || (!isset($context["filters"]["tfilters"]) && count($request->get('tfilters', [])) < 1))){
            return;
        }
        $isApiRoute = $request->get('isApiRoute',false);
        if ($isApiRoute){
            $query = $this->filterService->filterTFilterWithRequest(request: $request, query: $query);
        }
        if (!$isApiRoute){
            $query = $this->filterService->filterTFilterWithContext(context: $context,  query: $query);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
