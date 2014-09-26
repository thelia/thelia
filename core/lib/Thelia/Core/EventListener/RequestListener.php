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

namespace Thelia\Core\EventListener;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Authentication\AdminTokenAuthenticator;
use Thelia\Core\Security\Authentication\CustomerTokenAuthenticator;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AdminLog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Class RequestListener
 * @package Thelia\Core\EventListener
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class RequestListener implements EventSubscriberInterface
{

    use \Thelia\Tools\RememberMeTrait;

    /**
     *
     * @var \Thelia\Core\Translation\Translator
     */
    private $translator;

    /**
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterfac $container
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function registerValidatorTranslator(GetResponseEvent $event)
    {
        /** @var \Thelia\Core\HttpFoundation\Request $request */
        $request = $event->getRequest();
        $lang = $request->getSession()->getLang();
        $vendorDir = THELIA_ROOT.'core'.DS.'vendor';
        $vendorFormDir = $vendorDir.DS.'symfony'.DS.'form'.DS.'Symfony'.DS.'Component'.DS.'Form';
        $vendorValidatorDir =
            $vendorDir.DS.'symfony'.DS.'validator'.DS.'Symfony'.DS.'Component'.DS.'Validator';

        $this->translator->addResource(
            'xlf',
            sprintf($vendorFormDir.DS.'Resources'.DS.'translations'.DS.'validators.%s.xlf', $lang->getCode()),
            $lang->getLocale(),
            'validators'
        );
        $this->translator->addResource(
            'xlf',
            sprintf($vendorValidatorDir.DS.'Resources'.DS.'translations'.DS.'validators.%s.xlf', $lang->getCode()),
            $lang->getLocale(),
            'validators'
        );
    }

    public function rememberMeLoader(GetResponseEvent $event)
    {
        /** @var \Thelia\Core\HttpFoundation\Request $request */
        $request = $event->getRequest();
        /** @var \Thelia\Core\HttpFoundation\Session\Session $session */
        $session = $request->getSession();

        // Check customer remember me token
        if (null === $customer = $session->getCustomerUser()) {
            // try to get the remember me cookie
            $cookieCustomerName = ConfigQuery::read('customer_remember_me_cookie_name', 'crmcn');
            $cookie = $this->getRememberMeKeyFromCookie(
                $request,
                $cookieCustomerName
            );

            if (null !== $cookie) {
                // try to log
                $authenticator = new CustomerTokenAuthenticator($cookie);

                try {
                    // If have found a user, store it in the security context
                    $user = $authenticator->getAuthentifiedUser();

                    $session->setCustomerUser($user);
                } catch (TokenAuthenticationException $ex) {
                    //$this->adminLogAppend("admin", "LOGIN", "Token based authentication failed.");

                    // Clear the cookie
                    $this->clearRememberMeCookie($cookieCustomerName);
                }
            }

        }

        // Check admin remember me token
        if (null === $admin = $session->getAdminUser()) {

            // try to get the remember me cookie
            $cookieAdminName = ConfigQuery::read('admin_remember_me_cookie_name', 'armcn');
            $cookie = $this->getRememberMeKeyFromCookie(
                $request,
                $cookieAdminName
            );

            if (null !== $cookie) {

                // try to log
                $authenticator = new AdminTokenAuthenticator($cookie);

                try {
                    // If have found a user, store it in the security context
                    $user = $authenticator->getAuthentifiedUser();

                    $session->setAdminUser($user);

                    $this->applyUserLocale($user, $session);

                    AdminLog::append("admin", "LOGIN", "Authentication successful", $request, $user, false);
                } catch (TokenAuthenticationException $ex) {
                    AdminLog::append("admin", "LOGIN", "Token based authentication failed.", $request);

                    // Clear the cookie
                    $this->clearRememberMeCookie($cookieAdminName);
                }
            }

        }

    }


    protected function applyUserLocale(UserInterface $user, Session $session)
    {
        // Set the current language according to locale preference
        $locale = $user->getLocale();

        if (null === $lang = LangQuery::create()->findOneByLocale($locale)) {
            $lang = Lang::getDefaultLanguage();
        }

        $session->setLang($lang);
    }

    /**
     * Save the previous URL in session which is based on the referer header or the request, or
     * the _previous_url request attribute, if defined.
     *
     * If the value of _previous_url is "dont-save", the current referrer is not saved.
     *
     * @param \Symfony\Component\HttpKernel\Event\PostResponseEvent $event
     */
    public function registerPreviousUrl(PostResponseEvent  $event)
    {
        $request = $event->getRequest();

        $referrer = $request->attributes->get('_previous_url', null);

        if (null !== $referrer) {
            // A previous URL (or the keyword 'dont-save') has been specified.
            if ('dont-save' == $referrer) {
                // We should not save the current URL as the previous URL
                $referrer = null;
            }
        } else {
            // The current URL will become the previous URL
            $referrer = $request->getUri();
        }

        // Set previous URL, if defined
        if (null !== $referrer) {
            $session = $request->getSession();

            if (ConfigQuery::read("one_domain_foreach_lang", false) == 1) {
                $components = parse_url($referrer);
                $lang = LangQuery::create()
                    ->filterByUrl(sprintf("%s://%s", $components["scheme"], $components["host"]), ModelCriteria::LIKE)
                    ->findOne();

                if (null !== $lang) {
                    $session->setReturnToUrl($referrer);
                }
            } else {
                if (false !== strpos($referrer, $request->getSchemeAndHttpHost())) {
                    $session->setReturnToUrl($referrer);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ["registerValidatorTranslator", 128],
                ["rememberMeLoader", 128]
            ],
            KernelEvents::TERMINATE => [
                ["registerPreviousUrl", 128]
            ]
        ];
    }
}
