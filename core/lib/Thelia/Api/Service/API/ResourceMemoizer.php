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

namespace Thelia\Api\Service\API;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Per-request memoization for the in-process data access layer.
 *
 * The same resource is very often requested several times during a single
 * page render (duplicate calls, menus and lists looping over children). Each
 * call otherwise replays the whole API pipeline (route match, metadata,
 * Propel query, serialization). This holds the normalized result for the
 * duration of the request so identical calls are computed once.
 *
 * The store is reset on every main request, which keeps it correct under
 * long-running workers (FrankenPHP, RoadRunner) where the container is reused
 * across requests.
 */
class ResourceMemoizer
{
    /** @var array<string, object|array|null> */
    private array $store = [];

    /**
     * @param callable():(object|array|null) $compute
     */
    public function remember(string $key, callable $compute): object|array|null
    {
        if (\array_key_exists($key, $this->store)) {
            return $this->store[$key];
        }

        return $this->store[$key] = $compute();
    }

    public function clear(): void
    {
        $this->store = [];
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->clear();
        }
    }
}
