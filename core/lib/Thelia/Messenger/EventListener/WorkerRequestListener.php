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

namespace Thelia\Messenger\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Routing\RequestContext;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;

/**
 * Gives each job a request of its own, on the address of the shop.
 *
 * A worker serves no page, but the templates a job renders do as a page does: their
 * loops read the request and its session, and a mail of a module rendered in a job
 * failed on the first loop. The request is built from DEFAULT_URI (the request
 * context of the router), with an empty session, so the job sees no visitor, no
 * cart and the default language; it goes when the job is over, so the next one
 * starts without it.
 */
final readonly class WorkerRequestListener
{
    private const WORKER_REQUEST = '_thelia_worker_request';

    public function __construct(
        private RequestStack $requestStack,
        private RequestContext $requestContext,
    ) {
    }

    #[AsEventListener(priority: 4096)]
    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        if (null !== $this->requestStack->getMainRequest()) {
            return;
        }

        $request = Request::create($this->shopAddress());
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->attributes->set(self::WORKER_REQUEST, true);

        $this->requestStack->push($request);
    }

    #[AsEventListener(priority: -4096)]
    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->forgetTheRequest();
    }

    #[AsEventListener(priority: -4096)]
    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $this->forgetTheRequest();
    }

    private function forgetTheRequest(): void
    {
        // Only the request given here: a job run inside a page keeps the page's.
        while (true === $this->requestStack->getCurrentRequest()?->attributes->get(self::WORKER_REQUEST)) {
            $this->requestStack->pop();
        }
    }

    private function shopAddress(): string
    {
        $context = $this->requestContext;
        $port = 'https' === $context->getScheme() ? $context->getHttpsPort() : $context->getHttpPort();
        $defaultPort = 'https' === $context->getScheme() ? 443 : 80;

        return \sprintf(
            '%s://%s%s%s/',
            $context->getScheme(),
            $context->getHost(),
            $port === $defaultPort ? '' : ':'.$port,
            rtrim($context->getBaseUrl(), '/'),
        );
    }
}
