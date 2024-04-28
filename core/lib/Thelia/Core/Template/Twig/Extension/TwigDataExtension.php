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

use Thelia\Core\Service\DataAccessService;
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
            new TwigFunction('datas', [$this, 'getDatas']),
            new TwigFunction('loop', [$this, 'getLoop']),
        ];
    }

    public function getDatas(string $path, array $params = [], $locale = null, $cache = true): mixed
    {
        return $this->dataAccessService->datas($path, $params, $locale, $cache);
    }

    public function getLoop(string $path, array $params = []): mixed
    {
        return $this->dataAccessService->loop($path, $params);
    }
}
