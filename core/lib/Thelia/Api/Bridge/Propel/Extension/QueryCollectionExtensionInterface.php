<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;

interface QueryCollectionExtensionInterface
{
    public function applyToCollection(ModelCriteria $query, string $resourceClass, string $operationName = null, array $context = []);
}
