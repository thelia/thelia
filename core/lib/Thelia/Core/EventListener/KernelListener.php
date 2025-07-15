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

namespace Thelia\Core\EventListener;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Event\IsAdminEnvEvent;
use Thelia\Core\Event\SessionEvent;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\TheliaKernelEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

class KernelListener implements EventSubscriberInterface
{
    protected static Session $session;

    public function __construct(
        protected HttpKernelInterface $app,
        protected Translator $translator,
        protected EventDispatcherInterface $eventDispatcher,
        protected $cacheDir,
        protected $debug,
        protected $env,
    ) {
    }

    public function paramInit(RequestEvent $event): ?Response
    {
        if (HttpKernelInterface::MAIN_REQUEST === $event->getRequestType()) {
            $request = $event->getRequest();
            $response = $this->initParam($request);

            if ($response instanceof Response) {
                return $response;
            }

            $this->checkMultiDomainLang($request);
        }

        return null;
    }

    protected function checkMultiDomainLang(TheliaRequest $request): void
    {
        if (!ConfigQuery::isMultiDomainActivated()) {
            return;
        }

        if (TheliaRequest::$isAdminEnv) {
            return;
        }

        if (!$request->getSession() instanceof SessionInterface) {
            return;
        }

        $domainUrl = $request->getSession()->getLang()->getUrl();

        // if lang domain is different from current domain, redirect to the proper one
        if (!empty($domainUrl) && rtrim((string) $domainUrl, '/') !== $request->getSchemeAndHttpHost()) {
            $langs = LangQuery::create()
                ->filterByActive(true)
                ->filterByVisible(true)
                ->find();

            foreach ($langs as $lang) {
                $domainUrl = $lang->getUrl();

                if (rtrim((string) $domainUrl, '/') === $request->getSchemeAndHttpHost()) {
                    $request->getSession()->setLang($lang);
                    break;
                }
            }
        }
    }

    protected function initParam(TheliaRequest $request)
    {
        if (!$request->hasSession() || !$request->getSession() instanceof SessionInterface) {
            return;
        }

        $event = new IsAdminEnvEvent($request);

        $this->eventDispatcher->dispatch($event, IsAdminEnvEvent::class);

        if ($event->isAdminEnv()) {
            TheliaRequest::$isAdminEnv = true;

            if (($lang = $this->detectAdminLang($request)) instanceof Lang) {
                $request->getSession()->setAdminLang($lang);

                return $lang;
            }

            return;
        }

        $lang = $this->detectLang($request);

        if ($lang instanceof Response) {
            return $lang;
        }

        if ($lang instanceof Lang) {
            $request->getSession()->setLang($lang);
        }
    }

    protected function detectAdminLang(TheliaRequest $request): ?Lang
    {
        $requestedLangCodeOrLocale = $request->query->get('lang');

        if (null !== $requestedLangCodeOrLocale) {
            return LangQuery::create()->findOneByCode($requestedLangCodeOrLocale);
        }
        return Lang::getDefaultLanguage();
    }

    protected function detectLang(TheliaRequest $request): ?Lang
    {
        // first priority => lang parameter present in request (get or post)
        $requestedLangCodeOrLocale = $request->query->get('lang');

        // add a fallback on locale parameter
        if (null === $requestedLangCodeOrLocale) {
            $requestedLangCodeOrLocale = $request->query->get('locale');
        }

        // The lang parameter may contains a lang code (fr, en, ru) for Thelia < 2.2,
        // or a locale (fr_FR, en_US, etc.) for Thelia > 2.2.beta1
        if (null !== $requestedLangCodeOrLocale) {
            if (\strlen($requestedLangCodeOrLocale) > 2) {
                $lang = LangQuery::create()->filterByActive(true)->findOneByLocale($requestedLangCodeOrLocale);
            } else {
                $lang = LangQuery::create()->filterByActive(true)->findOneByCode($requestedLangCodeOrLocale);
            }

            if (null === $lang) {
                return Lang::getDefaultLanguage();
            }

            // if each lang has its own domain, we redirect the user to the proper one.
            if (ConfigQuery::isMultiDomainActivated()) {
                $domainUrl = $lang->getUrl();

                if (!empty($domainUrl)) {
                    // if lang domain is different from current domain, redirect to the proper one
                    if (rtrim((string) $domainUrl, '/') !== $request->getSchemeAndHttpHost()) {
                        return new RedirectResponse($domainUrl, Response::HTTP_MOVED_PERMANENTLY);
                    }

                    // the user is currently on the proper domain, nothing to change
                    return $lang;
                }

                Tlog::getInstance()->warning('The domain URL for language ' . $lang->getTitle() . ' (id ' . $lang->getId() . ') is not defined.');

                return Lang::getDefaultLanguage();
            }

            // one domain for all languages, the lang has to be set into session
            return $lang;
        }

        // Next, check if lang is defined in the current session. If not we have to set one.
        if (!$request->getSession()->getLang(false) instanceof Lang) {
            if (ConfigQuery::isMultiDomainActivated()) {
                // find lang with domain
                $domainLang = LangQuery::create()->filterByUrl($request->getSchemeAndHttpHost(), ModelCriteria::LIKE)->findOne();

                if (null !== $domainLang) {
                    return $domainLang;
                }
            }

            // At this point, set the lang to the default one.
            return Lang::getDefaultLanguage();
        }
        return Lang::getDefaultLanguage();
    }

    public function sessionInit(RequestEvent $event): void
    {
        $isApiRoute = preg_match('/^\/api\//', $event->getRequest()->getPathInfo());

        if ($isApiRoute) {
            $event->getRequest()->request->set('isApiRoute', $isApiRoute);
        }

        $request = $event->getRequest();
        $event = new SessionEvent($this->cacheDir, $this->debug, $this->env);
        $this->eventDispatcher->dispatch($event, TheliaKernelEvents::SESSION);
        self::$session = $event->getSession();
        $session = self::$session;

        $session->start();

        $request->setSession($session);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['paramInit', PHP_INT_MAX - 1],
                ['sessionInit', PHP_INT_MAX],
            ],
        ];
    }
}
