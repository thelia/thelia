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

namespace Thelia\Domain\CMS\Publication\Preview;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ViewCheckEvent;

/**
 * Lets the row a preview names through the visibility check of its page, and keeps the
 * answer out of every cache and every index.
 *
 * The check of `Thelia\Action\Content` and `Thelia\Action\Folder` turns an invisible row
 * down by dispatching `VIEW_*_ID_NOT_VISIBLE`, whose listener at priority 128 answers
 * 404. Running before it and stopping the propagation, for the previewed row only, is
 * what shows the page; any other row the same request would name still answers 404.
 *
 * The response listener runs before the shared cache policy (priority -1026), which
 * leaves alone an answer marked `no-store`.
 */
final readonly class PublicationPreviewSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function letThePreviewedRowThrough(ViewCheckEvent $event): void
    {
        $preview = PublicationPreview::fromRequest($this->requestStack->getMainRequest());

        if (null !== $preview && $preview->covers((string) $event->getView(), $event->getViewId())) {
            $event->stopPropagation();
        }
    }

    public function keepTheAnswerPrivate(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || null === PublicationPreview::fromRequest($event->getRequest())) {
            return;
        }

        $response = $event->getResponse();
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store', true);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::VIEW_CONTENT_ID_NOT_VISIBLE => ['letThePreviewedRowThrough', 192],
            TheliaEvents::VIEW_FOLDER_ID_NOT_VISIBLE => ['letThePreviewedRowThrough', 192],
            KernelEvents::RESPONSE => ['keepTheAnswerPrivate', -1000],
        ];
    }
}
