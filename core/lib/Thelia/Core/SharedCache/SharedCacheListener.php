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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SharedCacheListener
{
    private const FORWARDED_HEADERS = ['X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-Host'];

    public function __construct(
        private SharedCachePolicy $policy,
        private SharedCacheKeyCollector $keyCollector,
        #[Autowire(env: 'SHARED_CACHE_PURGER')]
        private string $purgerDriver = '',
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

        $mode = SharedCacheMode::fromShopConfiguration();

        if (SharedCacheMode::DISABLED === $mode) {
            return;
        }

        $this->tag($request, $response);

        if (!$this->policy->isShareable($request, $response)) {
            return;
        }

        $response->setVary(self::FORWARDED_HEADERS, false);

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

        $keys = [SharedCacheKey::ALL, ...$this->keyCollector->keys()];
        $driver = strtolower(trim($this->purgerDriver));

        if ('varnish' === $driver || !\in_array($driver, SharedCachePurgerFactory::DRIVERS, true)) {
            $response->headers->set('xkey', implode(' ', $keys));
        }

        if ('varnish' !== $driver) {
            $response->headers->set('Surrogate-Key', implode(', ', $keys));
        }
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
