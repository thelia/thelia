<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\HttpKernel\HttpCache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\HttpCache as BaseHttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use Thelia\Core\HttpFoundation\Request as TheliaRequest;

/**
 * Class HttpCache
 * @package Thelia\Core\HttpKernel\HttpCache
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class HttpCache extends BaseHttpCache implements HttpKernelInterface
{

    public function __construct(HttpKernelInterface $kernel, $options = array())
    {
        parent::__construct(
            $kernel,
            new Store($kernel->getCacheDir().'/http_cache'),
            new Esi(),
            array_merge(
                array('debug' => $kernel->isDebug()),
                $options
            )
        );
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        if (!($request instanceof \Thelia\Core\HttpFoundation\Request)) {
            $request = TheliaRequest::create(
                $request->getUri(),
                $request->getMethod(),
                $request->getMethod() == 'GET' ? $request->query->all() : $request->request->all(),
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                $request->getContent()
            );
        }

        return parent::handle($request, $type, $catch);
    }

}
