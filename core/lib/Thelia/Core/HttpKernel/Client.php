<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\HttpKernel;

use Symfony\Component\BrowserKit\Request as DomRequest;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Thelia\Core\HttpFoundation\Request;

/**
 * Class Client
 * @package Thelia\Core\HttpKernel
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
    protected function filterRequest(DomRequest $request)
    {
        $httpRequest = Request::create($request->getUri(), $request->getMethod(), $request->getParameters(), $request->getCookies(), $request->getFiles(), $request->getServer(), $request->getContent());

        $httpRequest->files->replace($this->filterFiles($httpRequest->files->all()));

        return $httpRequest;
    }
}
