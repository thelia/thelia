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

namespace TheliaTwig\Extension;

use Psr\Cache\InvalidArgumentException;
use TheliaTwig\Service\DataAccess\AttributeAccessService;
use TheliaTwig\Service\DataAccess\DataAccessService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataAccessExtension extends AbstractExtension
{
    public function __construct(
        private readonly DataAccessService $dataAccessService,
        private readonly AttributeAccessService $attributeAccessService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('resources', [$this, 'resources']),
            new TwigFunction('loop', [$this, 'getLoop']),
            new TwigFunction('loopCount', [$this, 'getLoopCount']),
            new TwigFunction('attr', [$this, 'attribute']),
        ];
    }

    /**
     * @throws \JsonException
     * @throws InvalidArgumentException
     */
    public function resources(string $path, array $params = []): array|object
    {
        return $this->dataAccessService->resources($path, $params);
    }

    public function attribute(string $type, string $attributeName): mixed
    {
        $methodName = 'attribute'.ucfirst($type);
        if (!method_exists($this->attributeAccessService, $methodName)) {
            throw new \RuntimeException(sprintf('Method %s not found in %s', $methodName, DataAccessService::class));
        }

        return $this->attributeAccessService->$methodName($attributeName);
    }

    public function getLoop(string $name, string $type, array $params = []): array
    {
        return $this->dataAccessService->loop($name, $type, $params);
    }

    public function getLoopCount(string $type, array $params = []): int
    {
        return $this->dataAccessService->loopCount($type, $params);
    }
}
