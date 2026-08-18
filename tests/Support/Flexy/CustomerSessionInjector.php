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
use Thelia\Model\Customer;

/**
 * Test-only subscriber that puts a customer in the Thelia session on every
 * kernel.request, the way a front-office login does. Front pages read the
 * session, not a firewall token, so this is all it takes to be logged in.
 *
 * Must run after KernelListener::initializeSession (priority PHP_INT_MAX) and
 * before the controllers that call checkAuth().
 */
final class CustomerSessionInjector implements EventSubscriberInterface
{
    private ?Customer $customer = null;

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function clear(): void
    {
        $this->customer = null;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (null === $this->customer) {
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

        $session->set('thelia.customer_user', $this->customer);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 200],
        ];
    }
}
