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

namespace Thelia\Core\Security\RateLimiter;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Counts login attempts on the two API login endpoints.
 *
 * Symfony's own login limiter is not used directly because it derives its wide
 * window from its narrow one by multiplying, which forbids reading either from
 * the environment. Both windows are declared as ordinary rate limiter policies
 * instead, so an operator sets each figure independently.
 *
 * Two windows are needed, as they are for the account emails a visitor can
 * trigger: the narrow one is keyed on caller and identifier, and stops passwords
 * being tried against one account; the wide one is keyed on the caller alone,
 * and stops one password being tried across many accounts.
 *
 * The front and the admin endpoints keep separate counters: an unrelated visitor
 * mistyping their password on the shop must not spend the budget of the shop's
 * own staff, and the other way round.
 *
 * Keys are hashed, so what ends up in the shared cache never spells out a caller
 * address or the identifier that was tried.
 */
final class ApiLoginRateLimiter extends AbstractRequestRateLimiter
{
    private const string ADMIN_SCOPE_PREFIX = '/api/admin';

    public function __construct(
        #[Autowire(service: 'limiter.api_login_per_client')]
        private readonly RateLimiterFactoryInterface $perClientLimiter,
        #[Autowire(service: 'limiter.api_login_per_client_and_identifier')]
        private readonly RateLimiterFactoryInterface $perClientAndIdentifierLimiter,
        #[\SensitiveParameter]
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function consume(Request $request): RateLimit
    {
        return $this->recordRefusal($request, parent::consume($request));
    }

    public function peek(Request $request): RateLimit
    {
        return $this->recordRefusal($request, parent::peek($request));
    }

    protected function getLimiters(Request $request): array
    {
        $caller = (string) $request->getClientIp();
        $identifier = $this->normalizeIdentifier(
            (string) $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME, ''),
        );
        $scope = str_starts_with($request->getPathInfo(), self::ADMIN_SCOPE_PREFIX) ? 'admin' : 'front';

        return [
            $this->perClientLimiter->create($this->key($scope, $caller)),
            $this->perClientAndIdentifierLimiter->create($this->key($scope, $identifier.'-'.$caller)),
        ];
    }

    /**
     * Leaves the delay where the authentication failure handler can read it: the
     * exception Symfony throws rounds the wait up to whole minutes, which would
     * tell a well-behaved client to wait a minute for a slot freeing in a second.
     */
    private function recordRefusal(Request $request, RateLimit $rateLimit): RateLimit
    {
        if (!$rateLimit->isAccepted() || 0 === $rateLimit->getRemainingTokens()) {
            RateLimitedResponse::remember($request, $rateLimit);
        }

        return $rateLimit;
    }

    private function normalizeIdentifier(string $identifier): string
    {
        return preg_match('//u', $identifier)
            ? mb_strtolower($identifier, 'UTF-8')
            : strtolower($identifier);
    }

    private function key(string $scope, string $data): string
    {
        return $scope.'-'.hash_hmac('sha256', $data, $this->secret);
    }
}
