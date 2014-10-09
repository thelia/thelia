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

namespace Thelia\Core\HttpFoundation;

use Symfony\Component\HttpFoundation\Request as BaseRequest;

/**
 * extends Symfony\Component\HttpFoundation\Request for adding some helpers
 *
 * Class Request
 * @package Thelia\Core\HttpFoundation
 * @author Manuel Raynaud <manu@thelia.net>
 */
class Request extends BaseRequest
{
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
