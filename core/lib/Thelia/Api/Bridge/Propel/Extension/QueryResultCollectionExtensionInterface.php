<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;

interface QueryResultCollectionExtensionInterface extends QueryCollectionExtensionInterface
{
    public function supportsResult(string $resourceClass, Operation $operation = null): bool;

    public function getResult(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []);
}
