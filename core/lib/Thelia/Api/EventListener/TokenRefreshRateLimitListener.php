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

namespace Thelia\Api\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Core\Security\RateLimiter\RateLimitAllowlist;
use Thelia\Core\Security\RateLimiter\RateLimitedResponse;

/**
 * Counts the calls to the two token refresh endpoints, per caller.
 *
 * A refresh token is a credential like any other: it is presented, checked and
 * either accepted or not, which is exactly the shape a caller can work through
 * a list with. The login endpoints are counted by the firewall, but these two
 * are plain controllers, so the counting happens here.
 *
 * The check runs at priority 7, between the firewall that authenticates at 8
 * and the API Platform read at 4, so a refused call never reaches the store the
 * refresh tokens live in.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class TokenRefreshRateLimitListener
{
    private const array REFRESH_PATHS = [
        '/api/admin/token/refresh',
        '/api/front/token/refresh',
    ];

    public function __construct(
        #[Autowire(service: 'limiter.api_token_refresh_per_client')]
        private RateLimiterFactoryInterface $limiter,
        private RateLimitAllowlist $allowlist,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!\in_array(rtrim($request->getPathInfo(), '/'), self::REFRESH_PATHS, true)) {
            return;
        }

        // No address to count in a console context, and none to count for a
        // caller the operator declared as exempt.
        $clientIp = $request->getClientIp();

        if (null === $clientIp || $this->allowlist->exempts($clientIp)) {
            return;
        }

        $rateLimit = $this->limiter->create($clientIp)->consume();

        if (!$rateLimit->isAccepted()) {
            $event->setResponse(RateLimitedResponse::forRateLimit($rateLimit));
        }
    }
}
