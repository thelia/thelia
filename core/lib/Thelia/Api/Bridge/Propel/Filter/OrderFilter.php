<?php

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;

class OrderFilter extends AbstractFilter
{
    public function apply(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        // TODO: Implement apply() method.
    }

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        // TODO: Implement filterProperty() method.
    }

    public function getDescription(string $resourceClass): array
    {
        // TODO: Implement getDescription() method.
    }
}
