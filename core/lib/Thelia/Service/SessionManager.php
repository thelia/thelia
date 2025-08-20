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

namespace Thelia\Service;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;

class SessionManager
{
    public function sessionIsStartable(RequestEvent $event): bool
    {
        if (!$event->isMainRequest()) {
            return false;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (
            str_starts_with($path, '/api/')
            || str_starts_with($path, '/_wdt/')
            || str_starts_with($path, '/_profiler')
            || $request->attributes->get('_stateless', false)
            || 'OPTIONS' === $request->getMethod()
        ) {
            return false;
        }

        if (headers_sent()) {
            return false;
        }

        if (!$request instanceof TheliaRequest) {
            return false;
        }

        if (!$request->hasSession()) {
            return false;
        }

        return true;
    }
}
