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

namespace BackOfficeDefaultTwigBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;

/**
 * Stamps the request as admin-typed when the path matches `/admin/...` so that
 * `TheliaRequest::fromAdmin()` and the TwigEngine URLService default router behave
 * correctly. The core never sets the flag itself.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 32)]
final readonly class AdminContextRequestListener
{
    private const ADMIN_PATH_PREFIX = '/admin';

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request instanceof TheliaRequest) {
            return;
        }

        if (str_starts_with($request->getPathInfo(), self::ADMIN_PATH_PREFIX)) {
            $request->setControllerType(BaseAdminController::CONTROLLER_TYPE);
        }
    }
}
