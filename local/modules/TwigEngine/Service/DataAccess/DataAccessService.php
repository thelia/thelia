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

namespace TwigEngine\Service\DataAccess;

use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use TwigEngine\Service\API\ResourceService;

class DataAccessService
{
    public function __construct(
        private readonly LoopDataAccessService $loopDataAccessService,
        private readonly ResourceService       $resourceService
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     */
    public function resources(string $path, array $parameters = []): object|array
    {
        return $this->resourceService->resources($path, $parameters);
    }

    /** @deprecated use new data access layer */
    public function loop(string $loopName, string $loopType, array $params = []): array
    {
        return $this->loopDataAccessService->theliaLoop($loopName, $loopType, $params);
    }

    /** @deprecated use new data access layer */
    public function loopCount(string $loopType, array $params = []): int
    {
        return $this->loopDataAccessService->theliaCount($loopType, $params);
    }
}
