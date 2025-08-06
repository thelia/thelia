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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Event\IsAdminEnvEvent;
use Thelia\Core\Event\SessionEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\TheliaKernelEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;
use Thelia\Service\Model\LangService;

class KernelListener
{
    protected static Session $session;

    public function __construct(
        protected HttpKernelInterface $app,
        protected Translator $translator,
        protected EventDispatcherInterface $eventDispatcher,
        protected LangService $langService,
        protected string $cacheDir,
        protected bool $debug,
        protected string $env,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: \PHP_INT_MAX - 1)]
    public function initializeLanguageAndAdmin(RequestEvent $event): ?Response
    {
        if (HttpKernelInterface::MAIN_REQUEST !== $event->getRequestType()) {
            return null;
        }
        $request = $event->getRequest();

        if (!$request instanceof TheliaRequest) {
            return null;
        }

        if (!$request->hasSession() || !($session = $request->getSession()) instanceof SessionInterface) {
            return null;
        }

        $event = new IsAdminEnvEvent($request);
        TheliaRequest::$isAdminEnv = $event->isAdminEnv();
        $this->eventDispatcher->dispatch($event, IsAdminEnvEvent::class);

        $response = $this->handleLang($session, $request);

        if ($response instanceof Response) {
            return $response;
        }

        $this->langService->syncMultiDomainLanguage($request);

        return null;
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: \PHP_INT_MAX)]
    public function initializeSession(RequestEvent $event): void
    {
        $isApiRoute = preg_match('/^\/api\//', $event->getRequest()->getPathInfo());

        if ($isApiRoute) {
            $event->getRequest()->request->set('isApiRoute', $isApiRoute);
        }

        $request = $event->getRequest();
        $event = new SessionEvent(
            $this->cacheDir,
            $this->debug,
            $this->env
        );
        $this->eventDispatcher->dispatch($event, TheliaKernelEvents::SESSION);
        self::$session = $event->getSession();
        $session = self::$session;

        $session->start();

        $request->setSession($session);
    }

    protected function handleLang(Session $session, Request $request): Response|Lang|null
    {
        if (true === TheliaRequest::$isAdminEnv) {
            $lang = $this->langService->resolveAdminLanguageFromRequest($request);
            $session->setAdminLang($lang);

            return $lang;
        }

        $langOrResponse = $this->langService->resolveFrontLanguageFromRequest($request);

        if ($langOrResponse instanceof Response) {
            return $langOrResponse;
        }

        if ($langOrResponse instanceof Lang) {
            $this->langService->setLang($langOrResponse);
        }

        return null;
    }
}
