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

namespace Thelia\Core\HttpFoundation\Session;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Empties the cart not saved yet between two requests served by the same process.
 *
 * Session keeps that cart in a static property, which PHP-FPM throws away with the
 * request but a worker (a runtime that keeps the kernel booted) does not: the next
 * visitor would start with it. It is emptied when a main request begins, and when
 * the kernel resets its services between two requests.
 */
final class TransientCartReset implements ResetInterface
{
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            Session::forgetTransientCart();
        }
    }

    public function reset(): void
    {
        Session::forgetTransientCart();
    }
}
