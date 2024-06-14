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

namespace Thelia\Core\Template\Twig\Extension;

use Psr\Cache\InvalidArgumentException;
use Thelia\Core\Template\Twig\Service\DataAccessService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigDataExtension extends AbstractExtension
{
    public function __construct(
        private readonly DataAccessService $dataAccessService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('resources', [$this, 'resources']),
            new TwigFunction('loop', [$this, 'getLoop']),
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

    public function getLoop(string $path, array $params = []): mixed
    {
        return $this->dataAccessService->loop($path, $params);
    }
}
