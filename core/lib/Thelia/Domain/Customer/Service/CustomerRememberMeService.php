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

namespace Thelia\Domain\Customer\Service;

use Thelia\Core\Security\Token\CookieTokenProvider;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Model\ConfigQuery;

class CustomerRememberMeService
{
    public function getRememberMeCookieName()
    {
        return ConfigQuery::read('customer_remember_me_cookie_name', 'crmcn');
    }

    public function getRememberMeCookieExpiration()
    {
        return ConfigQuery::read('customer_remember_me_cookie_expiration', 2592000 /* 1 month */);
    }

    public function createRememberMeCookie(UserInterface $user): void
    {
        (new CookieTokenProvider())->createCookie(
            $user,
            $this->getRememberMeCookieName(),
            $this->getRememberMeCookieExpiration()
        );
    }

    public function clearRememberMeCookie($cookieName): void
    {
        $ctp = new CookieTokenProvider();

        $ctp->clearCookie($cookieName);
    }
}
