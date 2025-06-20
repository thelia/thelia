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
namespace Thelia\Core\HttpKernel;

use Symfony\Component\BrowserKit\Request as DomRequest;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Thelia\Core\HttpFoundation\Request;

/**
 * Class Client.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Client extends HttpKernelBrowser
{
    /**
     * Converts the BrowserKit request to a HttpKernel request.
     *
     * @param DomRequest $request A Request instance
     *
     * @return Request A Request instance
     */
    protected function filterRequest(DomRequest $request): Request
    {
        $httpRequest = Request::create($request->getUri(), $request->getMethod(), $request->getParameters(), $request->getCookies(), $request->getFiles(), $request->getServer(), $request->getContent());

        $httpRequest->files->replace($this->filterFiles($httpRequest->files->all()));

        return $httpRequest;
    }
}
