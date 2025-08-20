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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Event\IsAdminEnvEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Service\Model\LangService;
use Thelia\Service\SessionManager;

class KernelListener
{
    public static ?Session $session = null;

    public function __construct(
        protected HttpKernelInterface $app,
        protected Translator $translator,
        protected EventDispatcherInterface $eventDispatcher,
        protected LangService $langService,
        protected SessionManager $sessionManager,
        protected string $cacheDir,
        protected bool $debug,
        protected string $env,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: \PHP_INT_MAX - 1)]
    public function checkIsApiRoute(RequestEvent $event): void
    {
        $isApiRoute = preg_match('/^\/api\//', $event->getRequest()->getPathInfo());

        if ($isApiRoute) {
            $event->getRequest()->request->set('isApiRoute', $isApiRoute);
        }
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 130)]
    public function warmupSession(RequestEvent $event): void
    {
        if (!$this->sessionManager->sessionIsStartable($event)) {
            return;
        }
        $request = $event->getRequest();

        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 80)]
    public function initializeLanguageAndAdmin(RequestEvent $event): ?Response
    {
        if (!$this->sessionManager->sessionIsStartable($event)) {
            return null;
        }
        /** @var Request $request */
        $request = $event->getRequest();
        /** @var Session $session */
        $session = $request->getSession();

        $isAdminEvent = new IsAdminEnvEvent($request);
        TheliaRequest::$isAdminEnv = $isAdminEvent->isAdminEnv();
        $this->eventDispatcher->dispatch($isAdminEvent, IsAdminEnvEvent::class);

        $response = $this->langService->handleLang($session, $request);

        if ($response instanceof Response) {
            return $response;
        }

        $this->langService->syncMultiDomainLanguage($request);

        return null;
    }
}
