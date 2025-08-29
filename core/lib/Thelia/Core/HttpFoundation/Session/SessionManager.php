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

namespace Thelia\Core\HttpFoundation\Session;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\Security\Authentication\AdminTokenAuthenticator;
use Thelia\Core\Security\Authentication\CustomerTokenAuthenticator;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Model\AdminLog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Tools\RememberMeTrait;

class SessionManager
{
    use RememberMeTrait;

    public function __construct(
        private readonly SessionFactory $sessionFactory,
    ) {
    }

    public function sessionIsStartable(RequestEvent $event): bool
    {
        if (!$event->isMainRequest()) {
            return false;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (
            str_starts_with($path, '/api/')
            || str_starts_with($path, '/_wdt/')
            || str_starts_with($path, '/_profiler')
            || $request->attributes->get('_stateless', false)
            || 'OPTIONS' === $request->getMethod()
        ) {
            return false;
        }

        if (headers_sent()) {
            return false;
        }

        if (!$request instanceof TheliaRequest) {
            return false;
        }

        return true;
    }

    public function getRememberMeCustomer(Request $request, Session $session, EventDispatcherInterface $dispatcher): void
    {
        // try to get the remember me cookie
        $cookieCustomerName = ConfigQuery::read('customer_remember_me_cookie_name', 'crmcn');
        $cookie = $this->getRememberMeKeyFromCookie(
            $request,
            $cookieCustomerName,
        );
        if (null === $cookie) {
            return;
        }
        // try to log
        $authenticator = new CustomerTokenAuthenticator($cookie);

        try {
            // If have found a user, store it in the security context
            /** @var Customer $user */
            $user = $authenticator->getAuthentifiedUser();
            $session->setCustomerUser($user);

            $dispatcher->dispatch(
                new CustomerLoginEvent($user),
                TheliaEvents::CUSTOMER_LOGIN,
            );
        } catch (TokenAuthenticationException) {
            // Clear the cookie
            $this->clearRememberMeCookie($cookieCustomerName);
        }
    }

    public function getRememberMeAdmin(Request $request, Session $session): void
    {
        // try to get the remember me cookie
        $cookieAdminName = ConfigQuery::read('admin_remember_me_cookie_name', 'armcn');
        $cookie = $this->getRememberMeKeyFromCookie(
            $request,
            $cookieAdminName,
        );
        if (null === $cookie) {
            return;
        }
        // try to log
        $authenticator = new AdminTokenAuthenticator($cookie);

        try {
            // If have found a user, store it in the security context
            $user = $authenticator->getAuthentifiedUser();
            if (null === $user) {
                throw new TokenAuthenticationException('No user found for this token');
            }
            $session->setAdminUser($user);

            $this->applyUserLocale($user, $session);

            AdminLog::append('admin', 'LOGIN', 'Authentication successful', $request, $user, false);
        } catch (TokenAuthenticationException) {
            AdminLog::append('admin', 'LOGIN', 'Token based authentication failed.', $request);

            // Clear the cookie
            $this->clearRememberMeCookie($cookieAdminName);
        }
    }

    protected function applyUserLocale(UserInterface $user, Session $session): void
    {
        // Set the current language according to locale preference
        $locale = $user->getLocale();

        if (null === $lang = LangQuery::create()
                ->filterByActive(true)
                ->filterByLocale($locale)
                ->findOne()) {
            $lang = Lang::getDefaultLanguage();
        }

        $session->setLang($lang);
    }
}
