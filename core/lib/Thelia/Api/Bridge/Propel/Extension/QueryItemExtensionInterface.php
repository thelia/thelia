<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use Propel\Runtime\ActiveQuery\ModelCriteria;

interface QueryItemExtensionInterface
{
    public function applyToItem(ModelCriteria $query, string $resourceClass, string $identifiers, string $operationName = null, array $context = []);
}
