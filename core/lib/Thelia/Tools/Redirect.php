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

namespace Thelia\Tools;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Log\Tlog;

class Redirect
{
    public static function exec($url, $status = 302, $cookies = array())
    {
        if (false == Tlog::getInstance()->showRedirect($url)) {
            $response = new RedirectResponse($url, $status);
            foreach ($cookies as $cookie)
            {
                if (!$cookie instanceof Cookie)
                {
                    throw new \InvalidArgumentException(sprintf('Third parameter is not a valid Cookie object.'));
                }
                $response->headers->setCookie($cookie);
            }
            $response->send();
        }
    }
}
