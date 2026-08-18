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
use Thelia\Core\HttpFoundation\Session\SessionFactory;
use Thelia\Core\HttpFoundation\Session\SessionManager;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Localization\Service\LangService;

class KernelListener
{
    public static ?Session $session = null;

    public function __construct(
        protected HttpKernelInterface $app,
        protected Translator $translator,
        protected EventDispatcherInterface $eventDispatcher,
        protected LangService $langService,
        protected SessionManager $sessionManager,
        protected SessionFactory $sessionFactory,
        protected string $cacheDir,
        protected bool $debug,
        protected string $env,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: \PHP_INT_MAX)]
    public function initializeSession(RequestEvent $event): void
    {
        if (headers_sent()) {
            return;
        }
        $request = $event->getRequest();
        if (!$request instanceof TheliaRequest) {
            $request = TheliaRequest::createFromBase($request);
        }

        if ($request->hasSession()) {
            return;
        }

        $session = $this->sessionFactory->createSession();
        $request->setSession($session);
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: \PHP_INT_MAX - 1)]
    public function checkIsApiRoute(RequestEvent $event): void
    {
        $isApiRoute = preg_match('/^\/api\//', $event->getRequest()->getPathInfo());

        if ($isApiRoute) {
            $event->getRequest()->request->set('isApiRoute', $isApiRoute);
        }
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: \PHP_INT_MAX - 2)]
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

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 128)]
    public function initializeLanguageAndAdmin(RequestEvent $event): void
    {
        // Everything a language redirect must not answer to is decided here: a sub request,
        // an api or a stateless route, a preflight OPTIONS, a request whose headers are
        // already on the wire. There is no console case to guard: this listener is only
        // ever called on a kernel request.
        if (!$this->sessionManager->sessionIsStartable($event)) {
            return;
        }
        /** @var Request $request */
        $request = $event->getRequest();
        /** @var Session $session */
        $session = $request->getSession();

        $isAdminEvent = new IsAdminEnvEvent($request);
        TheliaRequest::$isAdminEnv = $isAdminEvent->isAdminEnv();
        $this->eventDispatcher->dispatch($isAdminEvent, IsAdminEnvEvent::class);

        $response = $this->langService->handleLang($session, $request);

        // Symfony discards what a listener returns and reads only what setResponse() puts
        // on the event. This response used to be returned, a habit kept from the stack
        // middleware this listener was made of, whose handle() did answer with it: the
        // redirect to the domain of the requested language has been computed and thrown
        // away on every request ever since.
        if ($response instanceof Response) {
            $event->setResponse($response);

            return;
        }

        $this->langService->syncMultiDomainLanguage($request);
    }
}
