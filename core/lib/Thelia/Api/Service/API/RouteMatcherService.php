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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

readonly class RouteMatcherService
{
    public function matchRoute(RouterInterface $router, Request $apiRequest): array
    {
        $context = $router->getContext();
        $context->setMethod(Request::METHOD_GET);
        $context->setPathInfo($apiRequest->getPathInfo());

        return $router->matchRequest($apiRequest);
    }
}
