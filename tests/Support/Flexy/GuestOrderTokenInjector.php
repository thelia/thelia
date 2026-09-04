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

namespace Thelia\Tests\Support\Flexy;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Test-only subscriber that puts a guest order tracking token in the session, the way
 * placing an order without an account does.
 *
 * Written once and then forgotten by this subscriber: the session keeps it from there
 * on, which is the whole point of the tests that use it — what happens to a token
 * nobody writes again.
 *
 * Must run after KernelListener::initializeSession (priority PHP_INT_MAX) and before
 * the controllers that read the token.
 */
final class GuestOrderTokenInjector implements EventSubscriberInterface
{
    private const PLACED_ORDER_TOKEN_KEY = 'flexy.guest_order_token';

    private ?string $token = null;

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function clear(): void
    {
        $this->token = null;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (null === $this->token) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->hasSession(true)) {
            return;
        }

        $session = $request->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        $session->set(self::PLACED_ORDER_TOKEN_KEY, $this->token);
        $this->token = null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 200],
        ];
    }
}
