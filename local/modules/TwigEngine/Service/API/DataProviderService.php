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

readonly class DataProviderService
{
    public function __construct(
        private MetadataService $metadataService
    ) {
    }

    public function fetchData(
        Operation $operation,
        array $uriVariables,
        array $context
    ): object|array {
        return $this->metadataService->getProvider($operation)->provide($operation, $uriVariables, $context);
    }
}
