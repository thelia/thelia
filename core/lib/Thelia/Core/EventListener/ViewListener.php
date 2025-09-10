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
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\TheliaHttpKernel;
use Thelia\Core\View\ViewRenderer;

/**
 * ViewSubscriber Main class subscribing to view http response.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ViewListener
{
    public const IGNORE_THELIA_VIEW = 'ignore_thelia_view';

    public function __construct(
        protected ViewRenderer $viewRenderer,
    ) {
    }

    #[AsEventListener(event: KernelEvents::VIEW, priority: 0)]
    public function onKernelView(ViewEvent $event): void
    {
        $request = $event->getRequest();

        if (true === $request->attributes->get(TheliaHttpKernel::IGNORE_THELIA_VIEW, false)) {
            return;
        }
        $response = $this->viewRenderer->render($request);
        $event->setResponse($response);
    }
}
