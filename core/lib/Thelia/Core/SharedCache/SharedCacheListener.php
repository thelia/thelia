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
use Thelia\Model\ConfigQuery;

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

        $response->setVary($this->forwardedHeaders(), false);

        $ttl = SharedCachePolicy::ttl();

        if (SharedCacheMode::ANNOUNCE === $mode) {
            if ($this->carriesOnlyTheCookiesAProxyMayStrip($request, $response)) {
                $response->headers->set('Surrogate-Control', 'max-age='.$ttl);
            }

            return;
        }

        $this->makePublic($response, $ttl);
    }

    private function tag(Request $request, Response $response): void
    {
        if (!$this->keyCollector->isComplete() || !$this->policy->isTaggable($request, $response)) {
            return;
        }

        $keys = array_values(array_unique([SharedCacheKey::ALL, ...$this->keyCollector->keys(), ...$this->policy->routeKeys($request)]));
        $driver = strtolower(trim($this->purgerDriver));

        if ('varnish' === $driver || !\in_array($driver, SharedCachePurgerFactory::DRIVERS, true)) {
            $response->headers->set('xkey', implode(' ', $keys));
        }

        if ('varnish' !== $driver) {
            $response->headers->set('Surrogate-Key', implode(', ', $keys));
        }
    }

    /**
     * The absolute URLs of a page are built from the forwarded headers the shop
     * trusts: a page is only the same page for two visitors who sent the same
     * values, whatever the proxy lets through.
     *
     * @return list<string>
     */
    private function forwardedHeaders(): array
    {
        $headers = self::FORWARDED_HEADERS;

        if ([] === Request::getTrustedProxies()) {
            return $headers;
        }

        $trusted = Request::getTrustedHeaderSet();

        if (0 !== ($trusted & Request::HEADER_X_FORWARDED_PREFIX)) {
            $headers[] = 'X-Forwarded-Prefix';
        }

        if (0 !== ($trusted & Request::HEADER_FORWARDED)) {
            $headers[] = 'Forwarded';
        }

        return $headers;
    }

    private function carriesOnlyTheCookiesAProxyMayStrip(Request $request, Response $response): bool
    {
        $sessionName = $request->hasSession() ? $request->getSession()->getName() : 'PHPSESSID';
        $cartName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionName) {
                continue;
            }

            if ($cookie->getName() === $cartName && $cookie->isCleared()) {
                continue;
            }

            return false;
        }

        return true;
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
