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

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\Token\CookieTokenProvider;
use Thelia\Core\Security\User\UserInterface;

/**
 * Trait RememberMeTrait
 * @package Thelia\Tools
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
trait RememberMeTrait
{
    /**
     * Get the remember me key from the cookie.
     *
     * @return string hte key found, or null if no key was found.
     */
    protected function getRememberMeKeyFromCookie(Request $request, $cookieName)
    {
        $ctp = new CookieTokenProvider();

        return $ctp->getKeyFromCookie($request, $cookieName);
    }

    /**
     * Create the remember me cookie for the given user.
     */
    protected function createRememberMeCookie(UserInterface $user, $cookieName, $cookieExpiration)
    {
        $ctp = new CookieTokenProvider();

        $ctp->createCookie(
            $user,
            $cookieName,
            $cookieExpiration
        );
    }

    /**
     * Clear the remember me cookie.
     */
    protected function clearRememberMeCookie($cookieName)
    {
        $ctp = new CookieTokenProvider();

        $ctp->clearCookie($cookieName);
    }
}
