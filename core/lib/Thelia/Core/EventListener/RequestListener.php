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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Event\Currency\CurrencyChangeEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Authentication\AdminTokenAuthenticator;
use Thelia\Core\Security\Authentication\CustomerTokenAuthenticator;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AdminLog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Tools\RememberMeTrait;

/**
 * Class RequestListener
 * @package Thelia\Core\EventListener
 * @author manuel raynaud <manu@raynaud.io>
 */
class RequestListener implements EventSubscriberInterface
{
    use RememberMeTrait;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var \Thelia\Core\Translation\Translator */
    private $translator;

    public function __construct(Translator $translator, EventDispatcherInterface $eventDispatcher)
    {
        $this->translator = $translator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function registerValidatorTranslator(RequestEvent $event)
    {
        /** @var \Thelia\Core\HttpFoundation\Request $request */
        $request = $event->getRequest();
        $lang = $request->getSession()->getLang();

        $vendorFormDir = THELIA_VENDOR . 'symfony' . DS . 'form';
        $vendorValidatorDir = THELIA_VENDOR . 'symfony' . DS . 'validator';

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

    public function rememberMeLoader(RequestEvent $event)
    {
        /** @var \Thelia\Core\HttpFoundation\Request $request */
        $request = $event->getRequest();

        /** @var \Thelia\Core\HttpFoundation\Session\Session $session */
        $session = $request->getSession();

        if (null === $session->getCustomerUser()) {
            // Check customer remember me token
            $this->getRememberMeCustomer($request, $session, $this->eventDispatcher);
        }

        // Check admin remember me token
        if (null === $session->getAdminUser()) {
            $this->getRememberMeAdmin($request, $session);
        }
    }

    public function jsonBody(RequestEvent $event)
    {
        $request = $event->getRequest();
        if (!\count($request->request->all()) && \in_array($request->getMethod(), array('POST', 'PUT', 'PATCH', 'DELETE'))) {
            if ('json' === $request->getFormat($request->headers->get('Content-Type'))) {
                $content = $request->getContent();
                if (!empty($content)) {
                    $data = json_decode($content, true);

                    if (null === $data) {
                        $event->setResponse(
                            new JsonResponse(["error" => "The given data is not a valid json"], 400)
                        );

                        $event->stopPropagation();

                        return;
                    }

                    if (!\is_array($data)) {
                        // This case happens for string like: "Foo", that json_decode returns as valid json
                        $data = [$data];
                    }

                    $request->request = new ParameterBag($data);
                }
            }
        }
    }

    /**
     * @param Request $request
     * @param Session $session
     * @param EventDispatcherInterface $dispatcher
     */
    protected function getRememberMeCustomer(Request $request, Session $session, EventDispatcherInterface $dispatcher)
    {
        // try to get the remember me cookie
        $cookieCustomerName = ConfigQuery::read('customer_remember_me_cookie_name', 'crmcn');
        $cookie             = $this->getRememberMeKeyFromCookie(
            $request,
            $cookieCustomerName
        );

        if (null !== $cookie) {
            // try to log
            $authenticator = new CustomerTokenAuthenticator($cookie);

            try {
                // If have found a user, store it in the security context
                /** @var Customer $user */
                $user = $authenticator->getAuthentifiedUser();

                $session->setCustomerUser($user);

                $dispatcher->dispatch(
                    new CustomerLoginEvent($user),
                    TheliaEvents::CUSTOMER_LOGIN
                );
            } catch (TokenAuthenticationException $ex) {
                // Clear the cookie
                $this->clearRememberMeCookie($cookieCustomerName);
            }
        }
    }

    /**
     * @param $request
     * @param $session
     */
    protected function getRememberMeAdmin(Request $request, Session $session)
    {
        // try to get the remember me cookie
        $cookieAdminName = ConfigQuery::read('admin_remember_me_cookie_name', 'armcn');
        $cookie          = $this->getRememberMeKeyFromCookie(
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

    protected function applyUserLocale(UserInterface $user, Session $session)
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

    /**
     * Save the previous URL in session which is based on the referer header or the request, or
     * the _previous_url request attribute, if defined.
     *
     * If the value of _previous_url is "dont-save", the current referrer is not saved.
     *
     * @param TerminateEvent $event
     */
    public function registerPreviousUrl(TerminateEvent  $event)
    {
        $request = $event->getRequest();

        if (!$request->isXmlHttpRequest() && $event->getResponse()->isSuccessful()) {
            $referrer = $request->attributes->get('_previous_url', null);

            $catalogViews = ['category', 'product'];

            $view = $request->attributes->get('_view', null);

            if (null !== $referrer) {
                // A previous URL (or the keyword 'dont-save') has been specified.
                if ('dont-save' === $referrer) {
                    // We should not save the current URL as the previous URL
                    $referrer = null;
                }
            } else {
                // The current URL will become the previous URL
                $referrer = $request->getUri();
            }

            // Set previous URL, if defined
            if (null !== $referrer) {
                /** @var Session $session */
                $session = $request->getSession();

                if (ConfigQuery::isMultiDomainActivated()) {
                    $components = parse_url($referrer);
                    $lang = LangQuery::create()
                        ->filterByUrl(sprintf("%s://%s", $components["scheme"], $components["host"]), ModelCriteria::LIKE)
                        ->findOne();

                    if (null !== $lang) {
                        $session->setReturnToUrl($referrer);

                        if (\in_array($view, $catalogViews)) {
                            $session->setReturnToCatalogLastUrl($referrer);
                        }
                    }
                } else {
                    if (false !== strpos($referrer, $request->getSchemeAndHttpHost())) {
                        $session->setReturnToUrl($referrer);

                        if (\in_array($view, $catalogViews)) {
                            $session->setReturnToCatalogLastUrl($referrer);
                        }
                    }
                }
            }
        }
    }

    public function checkCurrency(RequestEvent $event)
    {
        /** @var Request $request */
        $request = $event->getRequest();

        if ($request->query->has("currency")) {
            if (null !== $find = CurrencyQuery::create()
                    ->filterById($request->getSession()->getCurrency(true)->getId(), Criteria::NOT_EQUAL)
                    ->filterByVisible(true)
                    ->filterByCode($request->query->get("currency"))
                    ->findOne()
            ) {
                $request->getSession()->setCurrency($find);
                $this->eventDispatcher->dispatch(new CurrencyChangeEvent($find, $request), TheliaEvents::CHANGE_DEFAULT_CURRENCY);
            } else {
                $defaultCurrency = Currency::getDefaultCurrency();
                $request->getSession()->setCurrency($defaultCurrency);
                $this->eventDispatcher->dispatch(new CurrencyChangeEvent($defaultCurrency, $request), TheliaEvents::CHANGE_DEFAULT_CURRENCY);
            }
        }
    }

    /**
     * {@inheritdoc}
     * api
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['checkCurrency', 256],
                ["registerValidatorTranslator", 128],
                ["rememberMeLoader", 128],
                ['jsonBody', 128]
            ],
            KernelEvents::TERMINATE => [
                ["registerPreviousUrl", 128]
            ]
        ];
    }
}
