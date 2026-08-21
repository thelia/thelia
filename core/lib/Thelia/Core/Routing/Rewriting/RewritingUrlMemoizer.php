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

namespace Thelia\Core\Routing\Rewriting;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Per-request memoization of rewritten url lookups.
 *
 * URL::retrieve() otherwise runs one SELECT per (view, view id, locale), and a
 * single page routinely asks for the same key several times (a category
 * repeated in a menu and in a product listing, a product shown as itself and
 * as a cross-sell). This holds the outcome for the duration of the request,
 * including the outcome "no rewritten url for that key" as a first-class
 * negative result, so a second call never replays the query.
 *
 * No dependency on the HTTP Request itself: url generation runs from CLI
 * commands as well (imports, exports, sitemap generation) and must keep
 * working without one. The store is cleared on the next main request and on
 * every console command instead, so a long-running worker (FrankenPHP,
 * RoadRunner) or a batch of commands sharing one process never serves stale
 * data.
 */
class RewritingUrlMemoizer
{
    /** @var array<string, array{url: ?string, rewrittenUrl: ?string}> */
    private array $store = [];

    public function has(string $view, mixed $viewId, mixed $viewLocale): bool
    {
        return \array_key_exists($this->key($view, $viewId, $viewLocale), $this->store);
    }

    /**
     * @param callable():array{url: ?string, rewrittenUrl: ?string} $compute
     *
     * @return array{url: ?string, rewrittenUrl: ?string}
     */
    public function remember(string $view, mixed $viewId, mixed $viewLocale, callable $compute): array
    {
        $key = $this->key($view, $viewId, $viewLocale);

        if (\array_key_exists($key, $this->store)) {
            return $this->store[$key];
        }

        return $this->store[$key] = $compute();
    }

    public function set(string $view, mixed $viewId, mixed $viewLocale, ?string $url, ?string $rewrittenUrl): void
    {
        $this->store[$this->key($view, $viewId, $viewLocale)] = [
            'url' => $url,
            'rewrittenUrl' => $rewrittenUrl,
        ];
    }

    public function clear(): void
    {
        $this->store = [];
    }

    /**
     * The keys a page asks for barely change from one request to the next (a
     * couple dozen views, ids and locales), so a single opaque string key is
     * enough: no need to reason about collisions between a view name and an
     * id that happen to share a separator.
     */
    private function key(string $view, mixed $viewId, mixed $viewLocale): string
    {
        return $view.'|'.($viewId ?? '').'|'.($viewLocale ?? '');
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->clear();
        }
    }

    #[AsEventListener(event: ConsoleEvents::COMMAND)]
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->clear();
    }
}
