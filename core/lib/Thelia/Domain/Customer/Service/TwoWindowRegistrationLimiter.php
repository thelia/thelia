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

namespace Thelia\Domain\Customer\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Domain\Customer\EmailAddress;

/**
 * The count both registration limiters share ({@see CustomerRegistrationLimiter} and
 * {@see \Thelia\Api\Security\GuestRegistrationLimiter}): one window per caller, one per
 * address. Each of them builds it from its own two limiters; it is not a service.
 */
#[Exclude]
final readonly class TwoWindowRegistrationLimiter
{
    public function __construct(
        private RateLimiterFactoryInterface $perAddressLimiter,
        private RateLimiterFactoryInterface $perClientLimiter,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Counts one more attempt for this address, from this caller: null when it is allowed,
     * otherwise the number of seconds after which the caller may try again.
     */
    public function attempt(string $email): ?int
    {
        // No request in a CLI context: the per-client limit simply does not apply. It is
        // consumed first so that a caller who is already over it stops eating the budget
        // of the address, whose legitimate owner would then be turned away.
        $clientIp = $this->requestStack->getMainRequest()?->getClientIp();

        if (null !== $clientIp) {
            $perClient = $this->perClientLimiter->create($clientIp)->consume();

            if (!$perClient->isAccepted()) {
                return self::secondsToWait($perClient);
            }
        }

        // The normalisation the registration compares addresses with, or a caller buys a
        // fresh budget by changing the case of one letter.
        $perAddress = $this->perAddressLimiter
            ->create(hash('sha256', EmailAddress::normalize($email)))
            ->consume();

        return $perAddress->isAccepted() ? null : self::secondsToWait($perAddress);
    }

    private static function secondsToWait(RateLimit $refusal): int
    {
        return max(1, $refusal->getRetryAfter()->getTimestamp() - time());
    }
}
