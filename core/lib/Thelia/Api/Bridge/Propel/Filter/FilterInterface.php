<?php

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;

interface FilterInterface
{
    public function apply(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void;
}
