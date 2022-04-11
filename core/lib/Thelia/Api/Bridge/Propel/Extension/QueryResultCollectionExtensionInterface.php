<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use Propel\Runtime\ActiveQuery\ModelCriteria;

interface QueryResultCollectionExtensionInterface extends QueryCollectionExtensionInterface
{
    public function supportsResult(string $resourceClass, string $operationName = null): bool;

    /**
     * @return iterable
     */
    public function getResult(ModelCriteria $query, string $resourceClass, string $operationName = null, array $context = []);
}
