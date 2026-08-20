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
        // Sub-requests are never a front view to theme: LiveComponent batch
        // actions, among others, run through kernel.view sub-requests whose
        // attributes do not carry the route defaults.
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (true === $request->attributes->get(TheliaHttpKernel::IGNORE_THELIA_VIEW, false)) {
            return;
        }

        // A LiveComponent's default render action reaches this listener as a main
        // request (unlike a named LiveAction, dispatched through a kernel.view
        // sub-request the check above already skips). Both share this listener's
        // priority with LiveComponentSubscriber::onKernelView(), and are registered
        // first, so treating the component's return value as a Thelia view name
        // throws before LiveComponentSubscriber gets to render it - the same
        // component every dependent field change or periodic refresh re-renders.
        if ($request->attributes->has('_live_component')) {
            return;
        }
        $response = $this->viewRenderer->render($request);
        $event->setResponse($response);
    }
}
