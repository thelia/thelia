<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;

final readonly class ResourceAddonExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $this->apply($query, $resourceClass, $operation, $context);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $this->apply($query, $resourceClass, $operation, $context);
    }

    public function apply(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        foreach ($this->apiResourcePropelTransformerService->getResourceAddonDefinitions($resourceClass) as $extendClass) {
            \call_user_func($extendClass.'::extendQuery', $query, $operation, $context);
        }
    }
}
