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

namespace Thelia\Api\Service\API;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\CallableProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class DataProviderService
{
    public function __construct(
        #[Autowire(service: 'api_platform.state_provider.locator')]
        private CallableProvider $callableProvider,
    ) {
    }

    public function fetchData(
        Operation $operation,
        array $uriVariables,
        array $context,
    ): object|array|null {
        return $this->callableProvider->provide(
            $operation,
            $uriVariables,
            $context
        );
    }
}
