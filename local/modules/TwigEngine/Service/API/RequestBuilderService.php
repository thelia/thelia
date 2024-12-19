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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class RequestBuilderService
{
    public function createApiRequest(RequestStack $requestStack, string $path): Request
    {
        $currentRequest = $requestStack->getCurrentRequest();
        if (null === $currentRequest) {
            throw new \RuntimeException('No current request found');
        }

        return Request::create(
            $path,
            Request::METHOD_GET,
            [],
            $currentRequest->cookies->all(),
            [],
            $currentRequest->server->all()
        );
    }
}
