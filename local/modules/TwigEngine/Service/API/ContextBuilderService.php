<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TwigEngine\Service\API;

use ApiPlatform\Metadata\Operation;

readonly class ContextBuilderService
{
    public function buildContext(
        string $path,
        Operation $operation,
        string $resourceClass,
        array $uriVariables,
        array $parameters
    ): array {
        return [
            'path_info' => $path,
            'operation' => $operation,
            'uri_variables' => $uriVariables,
            'resource_class' => $resourceClass,
            'filters' => $parameters,
            'groups' => $operation->getNormalizationContext()['groups'] ?? null,
        ];
    }
}
