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

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Thelia\Core\Security\RateLimiter\RateLimitAllowlist;
use Thelia\Core\Security\RateLimiter\RateLimitedResponse;
use Thelia\Model\Admin;

/**
 * How fast one caller may call the API.
 *
 * It covers the whole surface rather than a list of paths: the API has no
 * refuse-by-default rule, its permissions are a list of exceptions, and a
 * limiter mounted the same way would inherit the same blind spot — a resource
 * nobody thought of would be the one left uncounted.
 *
 * Three budgets, because the callers are not comparable. An anonymous caller
 * costs nothing to create and is counted by address. An authenticated one is
 * counted by account, so a whole office behind one address is not held to a
 * single budget, and gets more room since it has something to lose. The
 * administration surface gets the most, because one back-office screen fans out
 * into several calls.
 *
 * The check runs at priority 7, between the firewall that authenticates at 8
 * and the API Platform read at 4, so a refused call does not go on to load and
 * serialize anything. It runs after the firewall on purpose: before it, nobody
 * knows who is calling, and every caller behind one address would share the
 * anonymous budget.
 *
 * The two login endpoints answer from inside the firewall, so they never reach
 * this listener; the firewall counts their attempts itself, on a much narrower
 * budget.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class ApiRateLimitListener
{
    private const string API_PREFIX = '/api';

    public function __construct(
        #[Autowire(service: 'limiter.api_anonymous')]
        private RateLimiterFactoryInterface $anonymousLimiter,
        #[Autowire(service: 'limiter.api_front_authenticated')]
        private RateLimiterFactoryInterface $frontLimiter,
        #[Autowire(service: 'limiter.api_admin')]
        private RateLimiterFactoryInterface $adminLimiter,
        private Security $security,
        private RateLimitAllowlist $allowlist,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), self::API_PREFIX)) {
            return;
        }

        if ($this->allowlist->exempts($request->getClientIp())) {
            return;
        }

        $limiter = $this->limiterFor($request);

        if (null === $limiter) {
            return;
        }

        $rateLimit = $limiter->consume();

        if (!$rateLimit->isAccepted()) {
            $event->setResponse(RateLimitedResponse::forRateLimit($rateLimit));
        }
    }

    private function limiterFor(Request $request): ?LimiterInterface
    {
        $user = $this->security->getUser();

        if ($user instanceof Admin) {
            return $this->adminLimiter->create($user->getUserIdentifier());
        }

        if ($user instanceof UserInterface) {
            return $this->frontLimiter->create($user->getUserIdentifier());
        }

        // A console context has no caller address to count, the way the account
        // email limiter already has none.
        $clientIp = $request->getClientIp();

        return null === $clientIp ? null : $this->anonymousLimiter->create($clientIp);
    }
}
