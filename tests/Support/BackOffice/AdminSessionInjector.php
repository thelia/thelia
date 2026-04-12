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

namespace Thelia\Tests\Support\BackOffice;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Model\Admin;

/**
 * Test-only subscriber that injects an admin user into the Thelia
 * session on every kernel.request. Registered dynamically during
 * back-office HTTP tests to bypass the admin firewall.
 *
 * Must run AFTER KernelListener::initializeSession (priority PHP_INT_MAX)
 * and KernelListener::warmupSession (priority PHP_INT_MAX - 2),
 * but BEFORE ControllerListener::adminFirewall (priority 128).
 */
final class AdminSessionInjector implements EventSubscriberInterface
{
    private ?Admin $admin = null;

    public function setAdmin(Admin $admin): void
    {
        $this->admin = $admin;
    }

    public function clear(): void
    {
        $this->admin = null;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (null === $this->admin) {
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

        $session->set('thelia.admin_user', $this->admin);
    }

    public static function getSubscribedEvents(): array
    {
        // After session init (PHP_INT_MAX, PHP_INT_MAX-2) but before
        // admin firewall (128).
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 200],
        ];
    }
}
