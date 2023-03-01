<?php

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use ApiPlatform\Api\FilterInterface as BaseFilterInterface;

interface FilterInterface extends BaseFilterInterface
{
    public function apply(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void;
}
