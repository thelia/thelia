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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SharedCacheListener
{
    public function __construct(
        private SharedCachePolicy $policy,
        private SharedCacheKeyCollector $keyCollector,
    ) {
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -1026)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $this->tag($request, $response);

        $mode = SharedCacheMode::fromShopConfiguration();

        if (SharedCacheMode::DISABLED === $mode || !$this->policy->isShareable($request, $response)) {
            return;
        }

        $ttl = SharedCachePolicy::ttl();

        if (SharedCacheMode::ANNOUNCE === $mode) {
            $response->headers->set('Surrogate-Control', 'max-age='.$ttl);

            return;
        }

        $this->makePublic($response, $ttl);
    }

    private function tag(Request $request, Response $response): void
    {
        if (!$this->keyCollector->isComplete() || !$this->policy->isTaggable($request, $response)) {
            return;
        }

        $keys = implode(' ', [SharedCacheKey::ALL, ...$this->keyCollector->keys()]);

        $response->headers->set('Surrogate-Key', $keys);
        $response->headers->set('xkey', $keys);
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
