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
namespace Thelia\Core\HttpFoundation;

use Symfony\Component\HttpFoundation\Request as BaseRequest;

/**
 * extends Symfony\Component\HttpFoundation\Request for adding some helpers
 *
 * Class Request
 * @package Thelia\Core\HttpFoundation
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Request extends BaseRequest
{

    private $excludeContent = array(
        'username',
        'password'
    );

    public function getProductId()
    {
        return $this->get("product_id");
    }

    public function getUriAddingParameters(array $parameters = null)
    {
        $uri = $this->getUri();

        $additionalQs = '';

        foreach ($parameters as $key => $value) {
            $additionalQs .= sprintf("&%s=%s", $key, $value);
        }

        if ('' == $this->getQueryString()) {
            $additionalQs = '?'. ltrim($additionalQs, '&');
        }

        return $uri . $additionalQs;
    }

    /**
     * Gets the Session.
     *
     * @return \Thelia\Core\HttpFoundation\Session\Session The session
     * @api
     */
    public function getSession()
    {
        return parent::getSession();
    }

    public function toString($withContent = true)
    {
        $string =
            sprintf('%s %s %s', $this->getMethod(), $this->getRequestUri(), $this->server->get('SERVER_PROTOCOL'))."\r\n".
            $this->headers."\r\n";

        if (true === $withContent) {
            $string .= $this->getContent();
        }

        return $string;
    }
}
