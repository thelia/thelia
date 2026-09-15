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

namespace Thelia\Core\HttpFoundation;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * The path of a request, spelled the way the router reads it.
 *
 * Request::getPathInfo() hands back the path as the client wrote it, percent-encoding
 * included. The router and the firewall both decode it once before matching, so a
 * request written as /api/%61dmin/... is routed to the admin API and yet does not start
 * with "/api/admin". Every listener that decides something from a path prefix has to
 * look at the decoded spelling, or that decision can be sidestepped by encoding one
 * letter. Decoded exactly once, as the router does, so the two never disagree.
 */
final class RequestPath
{
    public static function decoded(SymfonyRequest $request): string
    {
        return rawurldecode($request->getPathInfo());
    }
}
