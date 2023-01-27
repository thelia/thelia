<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;

interface QueryItemExtensionInterface
{
    public function applyToItem(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []);
}
