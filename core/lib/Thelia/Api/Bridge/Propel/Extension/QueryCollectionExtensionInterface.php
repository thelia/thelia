<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;

interface QueryCollectionExtensionInterface
{
    public function applyToCollection(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []);
}
