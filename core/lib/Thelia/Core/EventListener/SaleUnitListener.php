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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Domain\SaleUnit\Service\SaleUnitService;

/**
 * Installs the current sale unit on every front request, right after
 * KernelListener::initializeLanguageAndAdmin (priority 128) has settled the
 * admin flag this listener reads and the language whose domain handling must
 * not be disturbed.
 *
 * The back office is left alone: an administrator works across sale units and
 * will pick one through a selector of its own, not through the domain used to
 * reach the admin.
 */
final readonly class SaleUnitListener
{
    public function __construct(private SaleUnitService $saleUnitService)
    {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 127)]
    public function resolveSaleUnit(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (true === Request::$isAdminEnv) {
            return;
        }

        $this->saleUnitService->handleRequest($event->getRequest());
    }
}
