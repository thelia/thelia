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

namespace Thelia\Core\HttpKernel\Fragment;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Symfony\Component\HttpKernel\Fragment\InlineFragmentRenderer as SymfonyInlineFragmentRenderer;

/**
 * @author Fabien Potencier <gilles@thelia.net>
 */
class InlineFragmentRenderer extends SymfonyInlineFragmentRenderer
{
    /**
     * @param string $uri
     * @param Request $request
     * @return Request
     */
    protected function createSubRequest($uri, Request $request)
    {
        $cookies = $request->cookies->all();
        $server = $request->server->all();

        // Override the arguments to emulate a sub-request.
        // Sub-request object will point to localhost as client ip and real client ip
        // will be included into trusted header for client ip
        try {
            if ($trustedHeaderName = Request::getTrustedHeaderName(Request::HEADER_CLIENT_IP)) {
                $currentXForwardedFor = $request->headers->get($trustedHeaderName, '');

                $server['HTTP_'.$trustedHeaderName] = ($currentXForwardedFor ? $currentXForwardedFor.', ' : '').$request->getClientIp();
            }
        } catch (\InvalidArgumentException $e) {
            // Do nothing
        }

        $server['REMOTE_ADDR'] = '127.0.0.1';

        $subRequest = TheliaRequest::create($uri, 'get', array(), $cookies, array(), $server);
        if ($request->headers->has('Surrogate-Capability')) {
            $subRequest->headers->set('Surrogate-Capability', $request->headers->get('Surrogate-Capability'));
        }

        if ($session = $request->getSession()) {
            $subRequest->setSession($session);
        }

        return $subRequest;
    }
}
