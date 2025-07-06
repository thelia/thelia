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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;

class TFiltersProvider implements ProviderInterface
{
    public function __construct(
        protected FilterService $filterService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $resource = $uriVariables['resource'] ?? null;
        if (!$resource) {
            throw new \InvalidArgumentException('The "resource" parameter is required.');
        }

        return $this->filterService->getFilters(context: $context, resource: $resource);
    }
}
