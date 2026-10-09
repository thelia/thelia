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

namespace Thelia\Core\SharedCache;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SharedCacheListener
{
    public function __construct(private SharedCachePolicy $policy)
    {
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -1026)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $mode = SharedCacheMode::fromShopConfiguration();
        $response = $event->getResponse();

        if (SharedCacheMode::DISABLED === $mode || !$this->policy->isShareable($event->getRequest(), $response)) {
            return;
        }

        $ttl = SharedCachePolicy::ttl();

        if (SharedCacheMode::ANNOUNCE === $mode) {
            $response->headers->set('Surrogate-Control', 'max-age='.$ttl);

            return;
        }

        $this->makePublic($response, $ttl);
    }

    private function makePublic(Response $response, int $ttl): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        if ([] !== $response->headers->getCookies()) {
            return;
        }

        $response->headers->remove(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER);
        $response->headers->remove('Expires');
        $response->headers->removeCacheControlDirective('private');
        $response->headers->removeCacheControlDirective('must-revalidate');
        $response->headers->removeCacheControlDirective('max-age');
        $response->setPublic();
        $response->setSharedMaxAge($ttl);
    }
}
